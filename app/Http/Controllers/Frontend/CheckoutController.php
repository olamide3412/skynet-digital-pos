<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\Order;
use App\Models\StoreSetting;
use App\Services\PaystackService;
use App\Services\FlutterwaveService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class CheckoutController extends Controller
{
    public function index(Request $request)
    {
        $cart = $this->getCart($request);

        if (!$cart || $cart->items->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'Your cart is empty.');
        }

        $customer = auth('customer')->user();

        return Inertia::render('Frontend/Checkout/Index', [
            'cart'     => $cart->load('items.product'),
            'settings' => StoreSetting::allAsArray(),
            'customer' => $customer ? [
                'name'  => $customer->name,
                'email' => $customer->email,
                'phone' => $customer->phone ?? '',
            ] : null,
        ]);
    }

    public function process(Request $request)
    {
        $settings = StoreSetting::allAsArray();

        // Build allowed gateway list based on admin settings
        $allowed = [];
        if (($settings['paystack_enabled'] ?? '0') === '1')    $allowed[] = 'paystack';
        if (($settings['flutterwave_enabled'] ?? '0') === '1') $allowed[] = 'flutterwave';
        if (($settings['cod_enabled'] ?? '0') === '1')         $allowed[] = 'cod';

        $request->validate([
            'email'           => 'required|email',
            'name'            => 'required|string',
            'phone'           => 'nullable|string',
            'address'         => 'nullable|string',
            'delivery_method' => 'nullable|in:delivery,pickup',
            'gateway'         => 'required|in:' . implode(',', $allowed ?: ['paystack']),
        ]);

        $cart = $this->getCart($request);

        if (!$cart || $cart->items->isEmpty()) {
            return redirect()->route('cart.index')->with('error', 'Cart is empty.');
        }

        // ─── Calculate totals ─────────────────────────────────────────
        $subtotal = $cart->items->sum(fn ($item) => $item->price * $item->quantity);

        $tax = 0;
        if (($settings['tax_enabled'] ?? '0') === '1') {
            $tax = ($subtotal * floatval($settings['tax_rate'] ?? 0)) / 100;
        }

        $serviceFee = 0;
        if (($settings['service_fee_enabled'] ?? '0') === '1') {
            $serviceFee = floatval($settings['service_fee_amount'] ?? 0);
        }

        $shippingFee = 0;
        $deliveryMethod = $request->delivery_method ?? 'delivery';
        if ($deliveryMethod === 'delivery' && ($settings['shipping_enabled'] ?? '0') === '1') {
            $freeThreshold = floatval($settings['free_shipping_threshold'] ?? 0);
            if ($freeThreshold === 0.0 || $subtotal < $freeThreshold) {
                $shippingFee = floatval($settings['waybill_fee'] ?? 0);
            }
        }

        $totalAmount = $subtotal + $tax + $serviceFee + $shippingFee;

        // ─── Create Order ─────────────────────────────────────────────
        $order = Order::create([
            'customer_id'     => auth('customer')->id() ?? null,
            'guest_email'     => auth('customer')->check() ? null : $request->email,
            'total_amount'    => $totalAmount,
            'status'          => 'pending',
            'payment_method'  => $request->gateway,
            'billing_name'    => $request->name,
            'billing_email'   => $request->email,
            'billing_phone'   => $request->phone,
            'billing_address' => $request->address,
            'delivery_method' => $deliveryMethod,
        ]);

        foreach ($cart->items as $item) {
            $order->items()->create([
                'product_id' => $item->product_id,
                'quantity'   => $item->quantity,
                'price'      => $item->price,
                'variant'    => $item->variant,
            ]);
        }

        // Clear the cart
        $cart->items()->delete();
        $cart->delete();

        // ─── Payment Gateway Routing ──────────────────────────────────
        if ($request->gateway === 'cod') {
            return redirect()->route('checkout.success', $order->tracking_number)
                ->with('success', 'Order placed successfully! You will pay on delivery.');
        }

        if ($request->gateway === 'paystack') {
            $paystack = new PaystackService();
            $response = $paystack->initializePayment($order, $request->email);
            if ($response['status']) {
                $order->update(['payment_reference' => $response['data']['reference']]);
                return Inertia::location($response['data']['authorization_url']);
            }
        } elseif ($request->gateway === 'flutterwave') {
            $flutterwave = new FlutterwaveService();
            $response = $flutterwave->initializePayment($order, $request->email, $request->name);
            if ($response['status'] === 'success') {
                $order->update(['payment_reference' => $response['data']['tx_ref']]);
                return Inertia::location($response['data']['link']);
            }
        }

        return back()->with('error', 'Payment initialization failed. Please try again.');
    }

    public function callback(Request $request)
    {
        $reference = $request->input('reference') ?? $request->input('tx_ref');
        
        if ($reference) {
            $order = Order::where('payment_reference', $reference)->first();
            if ($order) {
                return redirect()->route('checkout.success', $order->tracking_number)
                                 ->with('success', 'Payment confirmed! Please keep your tracking number.');
            }
        }

        return redirect()->route('customer.orders')->with('success', 'Payment processing. You can check order status here.');
    }

    public function success($tracking)
    {
        $order = Order::with('items.product')->where('tracking_number', $tracking)->firstOrFail();

        return Inertia::render('Frontend/Checkout/Success', [
            'order' => $order
        ]);
    }

    public function paystackWebhook(Request $request)
    {
        $secretKey = config('services.paystack.secret_key');
        
        // Ensure request comes from Paystack
        if ($request->header('x-paystack-signature') !== hash_hmac('sha512', $request->getContent(), $secretKey)) {
            \Illuminate\Support\Facades\Log::warning('Invalid Paystack Webhook Signature', $request->all());
            return response()->json(['status' => 'error', 'message' => 'Invalid signature'], 401);
        }

        $event = $request->input('event');
        $data = $request->input('data');

        if ($event === 'charge.success') {
            $reference = $data['reference'];
            $order = Order::where('payment_reference', $reference)->first();

            if ($order && $order->status === 'pending') {
                // Optionally verify the amount matches
                if ($data['amount'] >= ($order->total_amount * 100)) {
                    $order->update(['status' => 'processing']);
                    \Illuminate\Support\Facades\Log::info("Paystack payment successful for Order #{$order->id}");
                }
            }
        }

        return response()->json(['status' => 'success']);
    }

    public function flutterwaveWebhook(Request $request)
    {
        $secretHash = config('services.flutterwave.secret_hash');
        $signature = $request->header('verif-hash');

        if (!$signature || $signature !== $secretHash) {
            \Illuminate\Support\Facades\Log::warning('Invalid Flutterwave Webhook Signature');
            return response()->json(['status' => 'error', 'message' => 'Invalid signature'], 401);
        }

        $event = $request->input('event');
        $data = $request->input('data');

        if ($event === 'charge.completed' && isset($data['status']) && $data['status'] === 'successful') {
            $txRef = $data['tx_ref'];
            $order = Order::where('payment_reference', $txRef)->first();

            if ($order && $order->status === 'pending') {
                if ($data['amount'] >= $order->total_amount) {
                    $order->update(['status' => 'processing']);
                    \Illuminate\Support\Facades\Log::info("Flutterwave payment successful for Order #{$order->id}");
                }
            }
        }

        return response()->json(['status' => 'success']);
    }

    protected function getCart(Request $request)
    {
        if (auth('customer')->check()) {
            return Cart::where('customer_id', auth('customer')->id())->first();
        }
        return Cart::where('session_id', $request->session()->getId())->first();
    }
}
