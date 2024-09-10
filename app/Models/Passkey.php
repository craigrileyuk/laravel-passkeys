<?php

namespace App\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Webauthn\PublicKeyCredentialSource;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Webauthn\Denormalizer\WebauthnSerializerFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Webauthn\AttestationStatement\AttestationStatementSupportManager;

class Passkey extends Model
{
    use HasFactory;

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'data' => 'array'
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function data(): Attribute
    {
        return Attribute::make(
            get: fn(string $value) => (new WebauthnSerializerFactory(AttestationStatementSupportManager::create()))
                ->create()
                ->deserialize($value, PublicKeyCredentialSource::class, 'json'),
            set: fn(PublicKeyCredentialSource $value) => [
                'credential_id' => $value->publicKeyCredentialId,
                'data' => (new WebauthnSerializerFactory(
                    AttestationStatementSupportManager::create()
                ))->create()->serialize(data: $value, format: 'json')
            ],
        );
    }
}
