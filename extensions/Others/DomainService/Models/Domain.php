<?php

namespace Paymenter\Extensions\Others\DomainService\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class Domain extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_ACTIVE = 'active';

    /** Past expiry, still within the TLD's grace_days — renews at the normal price. */
    public const STATUS_GRACE = 'grace';

    /** Past grace, within redemption_days — renews at the normal price plus the redemption fee. */
    public const STATUS_REDEMPTION = 'redemption';

    /** Past grace and redemption — lost at the registry; no longer renewable here. */
    public const STATUS_EXPIRED = 'expired';

    public const STATUS_TRANSFER_PENDING = 'transfer_pending';
    public const STATUS_TRANSFER_FAILED = 'transfer_failed';
    public const STATUS_CANCELLED = 'cancelled';

    /** Statuses a customer can still act on — see a manage page, pay to keep the domain. */
    public const LAPSED_STATUSES = [self::STATUS_GRACE, self::STATUS_REDEMPTION];

    protected $fillable = [
        'user_id', 'registrar_id', 'name', 'sld', 'tld', 'registrar_ref', 'currency', 'auth_code', 'status',
        'registered_at', 'expires_at', 'nameservers', 'locked', 'privacy', 'autorenew',
    ];

    protected $casts = [
        'nameservers' => 'array',
        'auth_code' => 'encrypted',
        'registered_at' => 'datetime',
        'expires_at' => 'datetime',
        'locked' => 'boolean',
        'privacy' => 'boolean',
        'autorenew' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function registrar()
    {
        return $this->belongsTo(DomainRegistrar::class, 'registrar_id');
    }

    public function invoices()
    {
        return $this->hasMany(DomainInvoice::class, 'domain_id');
    }

    public function tldModel(): ?DomainTld
    {
        return DomainTld::where('tld', $this->tld)->first();
    }

    /** The registrar driver that manages this domain. */
    public function driver()
    {
        return $this->registrar->driver();
    }
}
