<?php

namespace Paymenter\Extensions\Others\DomainService\Models;

use App\Models\Invoice;
use Illuminate\Database\Eloquent\Model;

class DomainInvoice extends Model
{
    public const ACTION_REGISTER = 'register';
    public const ACTION_RENEW = 'renew';
    public const ACTION_TRANSFER = 'transfer';

    protected $fillable = [
        'invoice_id', 'domain_id', 'action', 'years', 'processed',
    ];

    protected $casts = [
        'years' => 'integer',
        'processed' => 'boolean',
    ];

    public function invoice()
    {
        return $this->belongsTo(Invoice::class);
    }

    public function domain()
    {
        return $this->belongsTo(Domain::class, 'domain_id');
    }
}
