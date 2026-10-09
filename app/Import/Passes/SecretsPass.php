<?php

namespace App\Import\Passes;

use App\Enums\Visibility;
use App\Import\ImportContext;
use App\Import\LegacyEncrypt;
use App\Import\Pass;
use App\Models\Secret;
use Illuminate\Database\Connection;
use Illuminate\Support\Carbon;
use RuntimeException;

/**
 * tkwiki `secret` → secrets: values decrypted with the old key and saved through the model, whose
 * `encrypted` casts re-encrypt them with APP_KEY. Runs before revisions so placeholders resolve.
 */
class SecretsPass implements Pass
{
    /** Columns tkwiki stores encrypted. */
    public const FIELDS = ['url', 'username', 'password', 'otp', 'keys', 'notes'];

    /** tkwiki Secret::PERM_* → visibility (public 0 becomes members: secrets are never public). */
    private const VISIBILITY = [1 => Visibility::Members, 2 => Visibility::Editors, 9 => Visibility::Private];

    /**
     * Check $key against the first encrypted value in the source, before anything is written.
     *
     * @throws \InvalidArgumentException when the key isn't hex
     * @throws RuntimeException when it doesn't decrypt the source's secrets
     */
    public static function checkKey(Connection $source, string $key): void
    {
        $crypt = new LegacyEncrypt($key);

        foreach (self::FIELDS as $field) {
            $value = $source->table('secret')->where($field, '!=', '')->whereNotNull($field)->value($field);
            if ($value !== null) {
                try {
                    $crypt->decrypt((string) $value);
                } catch (RuntimeException) {
                    throw new RuntimeException('The --old-key does not decrypt tkwiki\'s secrets. Use the system.encrypt value from its config.php.');
                }

                return;
            }
        }
    }

    public function name(): string
    {
        return 'Secrets';
    }

    public function count(ImportContext $context): int
    {
        return $context->source->db()->table('secret')->count();
    }

    public function run(ImportContext $context): int
    {
        $rows = $context->source->db()->table('secret')->orderBy('secret_id')->get();

        if ($context->oldKey === null) {
            if ($rows->isNotEmpty()) {
                $context->warn("Skipped {$rows->count()} secret(s): no --old-key to decrypt them. Their placeholders in pages became plain text.");
            }

            return 0;
        }

        $crypt = new LegacyEncrypt($context->oldKey);
        $imported = 0;

        foreach ($rows as $row) {
            $values = [];
            foreach (self::FIELDS as $field) {
                try {
                    $values[$field] = $crypt->decrypt((string) $row->{$field}) ?: null;
                } catch (RuntimeException) {
                    $values[$field] = null;
                    $context->warn("Secret \"{$row->name}\": its {$field} could not be decrypted; left empty.");
                }
            }

            $visibility = self::VISIBILITY[(int) $row->permission] ?? null;
            if ((int) $row->permission === 0) {
                $visibility = Visibility::Members;
                $context->warn("Secret \"{$row->name}\" was public; imported as members-only (secrets are never public).");
            } elseif ($visibility === null) {
                $visibility = Visibility::Private;
                $context->warn("Secret \"{$row->name}\" had unknown permission {$row->permission}; imported as private.");
            }

            $secret = new Secret;
            $secret->timestamps = false;
            $secret->forceFill($values + [
                'name' => (string) $row->name,
                'visibility' => $visibility,
                'publish' => (bool) $row->publish,
                'author_id' => $context->newId('user', (int) $row->user_id),
                'created_at' => $row->created ?? Carbon::now(),
                'updated_at' => $row->modified ?? Carbon::now(),
            ])->save();

            $stored = Secret::findOrFail($secret->id);
            foreach (self::FIELDS as $field) {
                if ($stored->{$field} !== $values[$field]) {
                    throw new RuntimeException("Secret \"{$row->name}\" did not survive re-encryption ({$field}).");
                }
            }

            $context->map('secret', (int) $row->secret_id, $secret->id);
            $context->secrets[md5($row->secret_id.'Secret')] = ['id' => $secret->id, 'name' => $secret->name];
            $imported++;
        }

        return $imported;
    }
}
