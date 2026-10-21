<?php
declare(strict_types=1);
namespace ReviewAutomation\Reviews\Model;
use Magento\Framework\Encryption\EncryptorInterface;
/** Short-lived encrypted form timestamp; CSRF and queue-token checks are separate. */
class TimestampValidator
{
    public function __construct(private readonly EncryptorInterface $encryptor) {}
    public function generateToken(): string { return $this->encryptor->encrypt((string) time()); }
    public function validateToken(?string $token): bool
    {
        if (!$token) { return false; }
        try {
            $value = $this->encryptor->decrypt($token);
            if (!ctype_digit($value)) { return false; }
            $elapsed = time() - (int) $value;
            return $elapsed >= 3 && $elapsed <= 3600;
        } catch (\Throwable $e) { return false; }
    }
}
