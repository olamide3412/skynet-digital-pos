<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PosCustomer extends Model
{
    protected $table = 'pos_customers';

    protected $fillable = [
        'branch_id', 'name', 'phone', 'whatsapp_number', 'email', 'address', 'gender', 'dob', 'note',
        'debt_bal', 'contact_name', 'contact_phone', 'contact_address', 'watch_list',
    ];

    protected function casts(): array
    {
        return [
            'watch_list' => 'boolean',
            'debt_bal'   => 'decimal:2',
            'dob'        => 'date',
        ];
    }

    public function setSameAsPhoneAttribute($value): void
    {
        // Virtual UI field; do not store in database
    }

    protected static function booted(): void
    {
        static::saving(function (PosCustomer $customer) {
            unset($customer->attributes['same_as_phone']);
        });

        static::creating(function (PosCustomer $customer) {
            unset($customer->attributes['same_as_phone']);
            if ($customer->address === null || trim((string) $customer->address) === '') {
                $customer->address = 'NA';
            }
            if ($customer->note === null || trim((string) $customer->note) === '') {
                $customer->note = 'No Note';
            }
            if ($customer->contact_name === null || trim((string) $customer->contact_name) === '') {
                $customer->contact_name = 'NA';
            }
            if ($customer->contact_phone === null || trim((string) $customer->contact_phone) === '') {
                $customer->contact_phone = 'NA';
            }
            if ($customer->contact_address === null || trim((string) $customer->contact_address) === '') {
                $customer->contact_address = 'NA';
            }
        });
    }

    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class, 'customer_id');
    }

    public function debtPayments(): HasMany
    {
        return $this->hasMany(DebtPayment::class, 'customer_id');
    }

    public function heldSales(): HasMany
    {
        return $this->hasMany(HeldSale::class, 'customer_id');
    }

    /**
     * Scope query to search by name, phone (including local/international and spaced variations),
     * whatsapp_number, and email.
     */
    public function scopeSearch($query, ?string $term)
    {
        $term = trim((string) $term);
        if ($term === '') {
            return $query;
        }

        $tokens = preg_split('/\s+/', $term, -1, PREG_SPLIT_NO_EMPTY);
        $cleanDigits = preg_replace('/[^0-9]/', '', $term);

        $phonePatterns = [];
        if (strlen($cleanDigits) >= 3) {
            $phonePatterns[] = $cleanDigits;
            if (str_starts_with($cleanDigits, '0')) {
                $noZero = ltrim($cleanDigits, '0');
                if (strlen($noZero) >= 3) {
                    $phonePatterns[] = $noZero;
                    $phonePatterns[] = '234' . $noZero;
                }
            }
            if (str_starts_with($cleanDigits, '234') && strlen($cleanDigits) >= 6) {
                $no234 = substr($cleanDigits, 3);
                if (strlen($no234) >= 3) {
                    $phonePatterns[] = $no234;
                    $phonePatterns[] = '0' . $no234;
                }
            }
            if (!str_starts_with($cleanDigits, '0') && !str_starts_with($cleanDigits, '234')) {
                $phonePatterns[] = '0' . $cleanDigits;
                $phonePatterns[] = '234' . $cleanDigits;
            }
        }
        $phonePatterns = array_unique(array_filter($phonePatterns));

        return $query->where(function ($q) use ($term, $tokens, $phonePatterns) {
            // 1. Email matching (case-insensitive substring)
            $q->where('email', 'like', "%{$term}%");

            // 2. Raw phone & whatsapp substring matching
            $q->orWhere('phone', 'like', "%{$term}%")
              ->orWhere('whatsapp_number', 'like', "%{$term}%");

            // 3. Normalized phone patterns (with stripped spaces, dashes, +)
            foreach ($phonePatterns as $pat) {
                $q->orWhere('phone', 'like', "%{$pat}%")
                  ->orWhere('whatsapp_number', 'like', "%{$pat}%")
                  ->orWhereRaw("REPLACE(REPLACE(REPLACE(phone, ' ', ''), '-', ''), '+', '') LIKE ?", ["%{$pat}%"])
                  ->orWhereRaw("REPLACE(REPLACE(REPLACE(whatsapp_number, ' ', ''), '-', ''), '+', '') LIKE ?", ["%{$pat}%"]);
            }

            // 4. Name tokens matching
            if (!empty($tokens)) {
                $q->orWhere(function ($nameQ) use ($tokens) {
                    foreach ($tokens as $token) {
                        $nameQ->where('name', 'like', "%{$token}%");
                    }
                });
            }
        });
    }
}

