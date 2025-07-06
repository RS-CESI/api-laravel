<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * @method static updateOrCreate(string[] $array, array $array1)
 * @method static create(array $array)
 */
class User extends Authenticatable
{

    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;
    use HasApiTokens, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role_id'
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
        ];
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }

    public function favoriteResources(): BelongsToMany
    {
        return $this->belongsToMany(Resource::class, 'user_resource_favorites');
    }

    public function progressions(): HasMany
    {
        return $this->hasMany(UserResourceProgression::class);
    }

    public function favorites(): HasMany
    {
        return $this->hasMany(UserResourceFavorite::class);
    }

    public function createdActivities(): HasMany
    {
        return $this->hasMany(ResourceActivity::class, 'created_by');
    }

    public function activityParticipations(): HasMany
    {
        return $this->hasMany(ActivityParticipant::class);
    }

    public function participatedActivities(): BelongsToMany
    {
        return $this->belongsToMany(ResourceActivity::class, 'activity_participants', 'user_id', 'resource_activity_id')
            ->withPivot(['status', 'role', 'score', 'joined_at'])
            ->withTimestamps();
    }

    /**
     * Ressources créées par l'utilisateur
     */
    public function resources(): HasMany
    {
        return $this->hasMany(Resource::class, 'created_by');
    }

    /**
     * Commentaires de l'utilisateur
     */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /**
     * Likes de commentaires de l'utilisateur
     */
    public function commentLikes(): HasMany
    {
        return $this->hasMany(CommentLike::class);
    }

    /**
     * Commentaires likés par l'utilisateur
     */
    public function likedComments(): BelongsToMany
    {
        return $this->belongsToMany(Comment::class, 'comment_likes')
            ->withTimestamps();
    }

    /**
     * Messages d'activité envoyés par l'utilisateur
     */
    public function activityMessages(): HasMany
    {
        return $this->hasMany(ActivityMessage::class);
    }

    /**
     * Ressources validées par l'utilisateur (modérateur)
     */
    public function validatedResources(): HasMany
    {
        return $this->hasMany(Resource::class, 'validated_by');
    }

    /**
     * Commentaires modérés par l'utilisateur
     */
    public function moderatedComments(): HasMany
    {
        return $this->hasMany(Comment::class, 'moderated_by');
    }

    // ==========================================
    // MÉTHODES DE VÉRIFICATION DES RÔLES
    // ==========================================

    /**
     * Vérifier si l'utilisateur a un rôle spécifique
     */
    public function hasRole(string $role): bool
    {
        return $this->role && $this->role->name === $role;
    }

    /**
     * Vérifier si l'utilisateur a l'un des rôles donnés
     */
    public function hasAnyRole(array $roles): bool
    {
        return $this->role && in_array($this->role->name, $roles);
    }

    /**
     * Vérifier si l'utilisateur peut modérer
     */
    public function canModerate(): bool
    {
        return $this->hasAnyRole(['moderator', 'administrator', 'super-administrator']);
    }

    /**
     * Vérifier si l'utilisateur est admin
     */
    public function isAdmin(): bool
    {
        return $this->hasAnyRole(['administrator', 'super-administrator']);
    }

    /**
     * Vérifier si l'utilisateur est super-admin
     */
    public function isSuperAdmin(): bool
    {
        return $this->hasRole('super-administrator');
    }

    /**
     * Vérifier si l'utilisateur est un simple citoyen
     */
    public function isCitizen(): bool
    {
        return $this->hasRole('citizen');
    }

    // ==========================================
    // MÉTHODES DE PERMISSIONS SUR LES RESSOURCES
    // ==========================================

    /**
     * Vérifier si l'utilisateur peut éditer une ressource
     */
    public function canEdit(Resource $resource): bool
    {
        // Super-admins et admins peuvent tout éditer
        if ($this->isAdmin()) {
            return true;
        }

        // Les utilisateurs peuvent éditer leurs propres ressources
        if ($resource->created_by === $this->id) {
            // Mais pas si elles sont publiées (sauf modérateurs)
            if (in_array($resource->status, ['published', 'suspended'])) {
                return $this->canModerate();
            }
            return true;
        }

        return false;
    }

    /**
     * Vérifier si l'utilisateur peut supprimer une ressource
     */
    public function canDelete(Resource $resource): bool
    {
        // Admins peuvent tout supprimer
        if ($this->isAdmin()) {
            return true;
        }

        // Les utilisateurs peuvent supprimer leurs brouillons
        return $resource->created_by === $this->id && $resource->status === 'draft';
    }

    /**
     * Vérifier si l'utilisateur peut voir une ressource
     */
    public function canView(Resource $resource): bool
    {
        // Ressources publiques : tout le monde
        if ($resource->visibility === 'public' && $resource->status === 'published') {
            return true;
        }

        // Admins et modérateurs voient tout
        if ($this->canModerate()) {
            return true;
        }

        // Créateur voit ses propres ressources
        if ($resource->created_by === $this->id) {
            return true;
        }

        // Ressources partagées : utilisateurs connectés
        if ($resource->visibility === 'shared' && $resource->status === 'published') {
            return true;
        }

        return false;
    }

    // ==========================================
    // MÉTHODES DE PERMISSIONS SUR LES ACTIVITÉS
    // ==========================================

    /**
     * Vérifier si l'utilisateur peut rejoindre une activité
     */
    public function canJoinActivity(ResourceActivity $activity): bool
    {
        // Vérifier si l'activité est ouverte
        if ($activity->status !== 'open') {
            return false;
        }

        // Vérifier la limite de participants
        if ($activity->participant_count >= $activity->max_participants) {
            return false;
        }

        // Activités privées : invitation requise
        if ($activity->is_private) {
            return $activity->participants()
                ->where('user_id', $this->id)
                ->whereIn('status', ['invited', 'accepted'])
                ->exists();
        }

        // Activités publiques : tout le monde peut rejoindre
        return true;
    }

    /**
     * Vérifier si l'utilisateur peut gérer une activité
     */
    public function canManageActivity(ResourceActivity $activity): bool
    {
        return $activity->created_by === $this->id || $this->isAdmin();
    }

    // ==========================================
    // MÉTHODES DE STATISTIQUES UTILISATEUR
    // ==========================================

    /**
     * Obtenir les statistiques personnelles de l'utilisateur
     */
    public function getPersonalStats(): array
    {
        return [
            'resources' => [
                'created' => $this->resources()->count(),
                'published' => $this->resources()->where('status', 'published')->count(),
                'drafts' => $this->resources()->where('status', 'draft')->count(),
                'total_views' => $this->resources()->sum('view_count'),
            ],
            'favorites' => [
                'count' => $this->favorites()->count(),
            ],
            'progressions' => [
                'total' => $this->progressions()->count(),
                'completed' => $this->progressions()->where('status', 'completed')->count(),
                'in_progress' => $this->progressions()->where('status', 'in_progress')->count(),
                'bookmarked' => $this->progressions()->where('status', 'bookmarked')->count(),
                'total_time' => $this->progressions()->sum('time_spent_minutes'),
            ],
            'activities' => [
                'created' => $this->createdActivities()->count(),
                'participated' => $this->activityParticipations()->whereIn('status', ['completed', 'participating'])->count(),
            ],
            'comments' => [
                'count' => $this->comments()->count(),
                'approved' => $this->comments()->where('status', 'approved')->count(),
            ],
        ];
    }

    // ==========================================
    // SCOPES POUR LES REQUÊTES
    // ==========================================

    /**
     * Scope pour les utilisateurs par rôle
     */
    public function scopeWithRole($query, string $role)
    {
        return $query->whereHas('role', function($q) use ($role) {
            $q->where('name', $role);
        });
    }

    /**
     * Scope pour les utilisateurs actifs
     */
    public function scopeActive($query)
    {
        return $query->whereNull('deleted_at'); // Si vous utilisez SoftDeletes
    }

    /**
     * Scope pour les modérateurs et admins
     */
    public function scopeModerators($query)
    {
        return $query->whereHas('role', function($q) {
            $q->whereIn('name', ['moderator', 'administrator', 'super-administrator']);
        });
    }

    /**
     * Scope pour les citoyens
     */
    public function scopeCitizens($query)
    {
        return $query->whereHas('role', function($q) {
            $q->where('name', 'citizen');
        });
    }

    // ==========================================
    // ACCESSEURS ET MUTATEURS
    // ==========================================

    /**
     * Accesseur pour le nom complet du rôle
     */
    public function getRoleNameAttribute(): string
    {
        return $this->role->name ?? 'citizen';
    }

    /**
     * Accesseur pour vérifier si l'utilisateur a vérifié son email
     */
    public function getIsVerifiedAttribute(): bool
    {
        return !is_null($this->email_verified_at);
    }

    /**
     * Accesseur pour obtenir l'initiale du nom
     */
    public function getInitialsAttribute(): string
    {
        $names = explode(' ', $this->name);
        $initials = '';

        foreach ($names as $name) {
            $initials .= strtoupper(substr($name, 0, 1));
        }

        return substr($initials, 0, 2); // Maximum 2 initiales
    }

    // ==========================================
    // MÉTHODES UTILITAIRES POUR L'API
    // ==========================================

    /**
     * Formater les données utilisateur pour l'API
     */
    public function toApiArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'role' => $this->role_name,
            'is_verified' => $this->is_verified,
            'initials' => $this->initials,
            'created_at' => $this->created_at,
            'permissions' => [
                'can_moderate' => $this->canModerate(),
                'is_admin' => $this->isAdmin(),
                'is_super_admin' => $this->isSuperAdmin(),
            ],
        ];
    }

    /**
     * Créer un token API pour l'utilisateur
     */
    public function createApiToken(string $name = 'api-token'): string
    {
        // Révoquer les anciens tokens
        $this->tokens()->delete();

        // Créer un nouveau token
        return $this->createToken($name)->plainTextToken;
    }
}
