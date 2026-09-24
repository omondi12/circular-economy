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

#[Fillable(['name', 'email', 'phone_number', 'password', 'role', 'is_active', 'supervisor_id'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    public const ROLE_ADMIN = 'admin';

    public const ROLE_RM = 'rm';

    public const ROLE_SUPERVISOR = 'supervisor';

    /**
     * A staff role focused on Requisitions only (2026-09-19): can approve/
     * decline/pay RM and Supervisor requests, and can submit their own -
     * but their own request needs a Supervisor's (or Admin's) approval,
     * never another Office Admin's, since office admins have no oversight
     * authority over each other. Deliberately does NOT get the rest of
     * /admin (clients, ministries, team accounts, audit log).
     */
    public const ROLE_OFFICE_ADMIN = 'office_admin';

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

    public function requisitions(): HasMany
    {
        return $this->hasMany(Requisition::class, 'requester_id');
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

    /**
     * RMs with no supervisor at all (2026-09-19: this happens whenever an
     * RM's supervisor is converted to a different role - updateUser()
     * correctly frees the RM rather than leaving them reporting to a
     * non-supervisor, but that RM's clients/ministries would then be
     * invisible to *every* supervisor's visibleTo() scope forever, since
     * they belong to nobody's team. StateCorporation/GovernmentEntity
     * scopeVisibleTo() treat these the same as unassigned - visible to
     * any supervisor - so nothing gets silently orphaned out of view.
     */
    public function scopeOrphanedRms($query)
    {
        return $query->where('role', self::ROLE_RM)->whereNull('supervisor_id');
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

    public function isOfficeAdmin(): bool
    {
        return $this->role === self::ROLE_OFFICE_ADMIN;
    }

    public function canPayRequisitions(): bool
    {
        return $this->isOfficeAdmin();
    }

    /**
     * Supervisors get the same admin-area access as admins (per the boss's
     * decision, 2026-09-05) - a distinct role so their actions are their
     * own in the audit log, rather than everyone sharing the admin login.
     * Office Admins are deliberately excluded - see the ROLE_OFFICE_ADMIN
     * docblock.
     */
    public function canAccessAdminArea(): bool
    {
        return in_array($this->role, [self::ROLE_ADMIN, self::ROLE_SUPERVISOR], true);
    }

    /**
     * Who can reach the requisitions approval page/actions at all - admins
     * and supervisors (as before), plus Office Admins now (2026-09-19).
     * Whether they can approve one specific request is a narrower question
     * - see canApproveRequisition() below.
     */
    public function canApproveRequisitions(): bool
    {
        return in_array($this->role, [self::ROLE_ADMIN, self::ROLE_SUPERVISOR, self::ROLE_OFFICE_ADMIN], true);
    }

    /**
     * Per-request approval authorization (2026-09-19) - the single source
     * of truth used by both RequisitionController (to authorize the
     * action) and the admin/public requisition views (to decide whether to
     * show the Approve/Decline buttons for that row). Admins are
     * unrestricted, including approving their own - same as everywhere
     * else in the app. Supervisors and Office Admins can approve anyone's
     * request except their own; an Office Admin's request additionally
     * needs a Supervisor (or Admin) - not another Office Admin, since
     * office admins have no oversight authority over each other.
     */
    public function canApproveRequisition(Requisition $requisition): bool
    {
        if (! $this->canApproveRequisitions()) {
            return false;
        }

        if ($this->isAdmin()) {
            return true;
        }

        if ($requisition->requester_id === $this->id) {
            return false;
        }

        if ($this->isOfficeAdmin() && $requisition->requester?->isOfficeAdmin()) {
            return false;
        }

        return true;
    }

    /**
     * Where this account lands after login and what the nav's primary
     * button points to - centralized here since it now differs per role
     * (Office Admin has no use for the full admin dashboard, only
     * Requisitions).
     */
    public function homeRouteName(): string
    {
        return match ($this->role) {
            self::ROLE_ADMIN, self::ROLE_SUPERVISOR => 'admin.dashboard',
            self::ROLE_OFFICE_ADMIN => 'admin.requisitions.index',
            default => 'rm.dashboard',
        };
    }

    public function homeLabel(): string
    {
        return match ($this->role) {
            self::ROLE_ADMIN, self::ROLE_SUPERVISOR => 'Admin',
            self::ROLE_OFFICE_ADMIN => 'Requisitions',
            default => 'My Dashboard',
        };
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
