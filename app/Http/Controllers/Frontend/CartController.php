<?php

namespace App\Http\Controllers\Frontend;

use App\Http\Controllers\Controller;
use App\Models\Cart;
use App\Models\CartItem;
use App\Models\Product;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Str;

class CartController extends Controller
{
    public function index(Request $request)
    {
        $cart = $this->getCart($request);
        
        return Inertia::render('Frontend/Cart/Index', [
            'cart' => $cart ? $cart->load('items.product') : null
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'product_id' => 'required|exists:products,id',
            'quantity' => 'required|integer|min:1',
            'variant' => 'nullable|string'
        ]);

        $product = Product::findOrFail($request->product_id);
        $cart = $this->getOrCreateCart($request);

        $cartItem = $cart->items()
                         ->where('product_id', $product->id)
                         ->where('variant', $request->variant)
                         ->first();
        
        $price = $product->discount_price > 0 ? $product->discount_price : $product->price;

        // Apply distinct variant pricing override if provided
        if ($request->variant && $product->variants) {
            foreach (is_string($product->variants) ? json_decode($product->variants, true) : $product->variants as $v) {
                if ($v['name'] === $request->variant && !empty($v['price'])) {
                    $price = $v['price'];
                    break;
                }
            }
        }

        if ($cartItem) {
            $cartItem->update([
                'quantity' => $cartItem->quantity + $request->quantity,
                'price' => $price
            ]);
        } else {
            $cart->items()->create([
                'product_id' => $product->id,
                'quantity' => $request->quantity,
                'price' => $price,
                'variant' => $request->variant
            ]);
        }

        return back()->with('success', 'Added to cart successfully.');
    }

    public function update(Request $request, CartItem $cartItem)
    {
        $request->validate([
            'quantity' => 'required|integer|min:1'
        ]);

        $cartItem->update(['quantity' => $request->quantity]);

        return back()->with('success', 'Cart updated.');
    }

    public function destroy(CartItem $cartItem)
    {
        $cartItem->delete();
        return back()->with('success', 'Removed from cart.');
    }

    protected function getCart(Request $request)
    {
        if (auth('customer')->check()) {
            return Cart::where('customer_id', auth('customer')->id())->first();
        }
        
        $sessionId = $request->session()->getId();
        return Cart::where('session_id', $sessionId)->first();
    }

    protected function getOrCreateCart(Request $request)
    {
        $cart = $this->getCart($request);
        
        if (!$cart) {
            $cart = Cart::create([
                'customer_id' => auth('customer')->id() ?? null,
                'session_id' => auth('customer')->check() ? null : $request->session()->getId()
            ]);
        }
        
        return $cart;
    }
}
