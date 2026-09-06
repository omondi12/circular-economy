<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'role', 'is_active', 'supervisor_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';

    public const ROLE_RM = 'rm';

    public const ROLE_SUPERVISOR = 'supervisor';

    /**
     * Demo accounts (DemoDataSeeder) all share this domain - excluded
     * rather than requiring a specific real domain like @amacplc.com, so
     * a real RM onboarded with any working email (e.g. a personal Gmail
     * address) is still picked up everywhere assignable RMs are listed.
     */
    private const DEMO_EMAIL_DOMAIN = '@demo.amac-circular.local';

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'is_active' => 'boolean',
        ];
    }

    public function collections(): HasMany
    {
        return $this->hasMany(Collection::class);
    }

    public function assignedMinistries(): HasMany
    {
        return $this->hasMany(GovernmentEntity::class, 'assigned_rm_id')->where('level', GovernmentEntity::LEVEL_MINISTRY);
    }

    public function assignedStateCorporations(): HasMany
    {
        return $this->hasMany(StateCorporation::class, 'assigned_rm_id');
    }

    /**
     * The supervisor an RM reports to (2026-09-06: each RM now belongs to
     * exactly one supervisor, who does that RM's client reporting).
     */
    public function supervisor(): BelongsTo
    {
        return $this->belongsTo(self::class, 'supervisor_id');
    }

    /**
     * The RMs under this supervisor. Only meaningful for a supervisor
     * account - an RM's own `rms()` is always empty.
     */
    public function rms(): HasMany
    {
        return $this->hasMany(self::class, 'supervisor_id');
    }

    public function isAdmin(): bool
    {
        return $this->role === self::ROLE_ADMIN;
    }

    public function isRm(): bool
    {
        return $this->role === self::ROLE_RM;
    }

    public function isSupervisor(): bool
    {
        return $this->role === self::ROLE_SUPERVISOR;
    }

    /**
     * Supervisors get the same admin-area access as admins (per the boss's
     * decision, 2026-09-05) - a distinct role so their actions are their
     * own in the audit log, rather than everyone sharing the admin login.
     */
    public function canAccessAdminArea(): bool
    {
        return in_array($this->role, [self::ROLE_ADMIN, self::ROLE_SUPERVISOR], true);
    }

    /**
     * Active, real (non-demo) RM accounts - the pool used everywhere an
     * RM can be assigned a ministry or client (Assign RMs, RM Performance,
     * client reports, the distribute commands).
     */
    public function scopeAssignableRms($query)
    {
        return $query->where('role', self::ROLE_RM)
            ->where('is_active', true)
            ->where('email', 'not like', '%'.self::DEMO_EMAIL_DOMAIN);
    }

    /**
     * Assignable RMs a given viewer is allowed to see/assign - an admin
     * sees every RM (unchanged); a supervisor sees only their own team, per
     * the boss's "they can only see the ones that are theirs" (2026-09-06).
     * The RM dropdown on Assign RMs, RM Performance, client reports, and
     * the Team Accounts list all filter through this one place.
     */
    public function scopeVisibleRmsFor($query, self $viewer)
    {
        $query->assignableRms();

        if ($viewer->isSupervisor()) {
            $query->where('supervisor_id', $viewer->id);
        }

        return $query;
    }
}
