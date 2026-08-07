<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'plan_id',
        'email_verified_at',
        'is_admin',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

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
            'is_admin' => 'boolean',
        ];
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function projects(): HasMany
    {
        return $this->hasMany(Project::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class);
    }

    public function adminAlerts(): HasMany
    {
        return $this->hasMany(AdminAlert::class);
    }

    public function currentSubscription(): ?Subscription
    {
        return $this->subscriptions()->latest('id')->get()->first(fn (Subscription $subscription): bool => $subscription->grantsAccess());
    }

    public function hasHostingAccess(): bool
    {
        return $this->is_admin || $this->currentSubscription() !== null;
    }

    public function canUseProject(Project $project): bool
    {
        if ($this->is_admin) {
            return true;
        }

        $planId = self::query()->whereKey($this->id)->value('plan_id');
        $plan = $planId ? Plan::query()->find($planId) : null;

        if (! $this->hasHostingAccess() || ! $plan || $project->user_id !== $this->id) {
            return false;
        }

        return $this->projects()->where('id', '<=', $project->id)->count() <= $plan->website_limit;
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return $panel->getId() === 'admin' && $this->is_admin;
    }
}
