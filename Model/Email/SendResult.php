<?php
declare(strict_types=1);

namespace ReviewAutomation\Reviews\Model\Email;

class SendResult
{
    public const TYPE_NUDGE = 'review_nudge';
    public const TYPE_EMAIL1 = 'email1';
    public const TYPE_EMAIL1_REMINDER = 'email1_reminder';
    public const TYPE_EMAIL2 = 'email2';
    public const TYPE_PRODUCT_REVIEW = 'product_review';

    public function __construct(
        private readonly bool $success,
        private readonly ?string $messageType,
        private readonly ?string $errorMessage,
        private readonly bool $usedMockData = false
    ) {
    }

    public function isSuccess(): bool
    {
        return $this->success;
    }

    /**
     * @return string|null one of the TYPE_* constants, or null when no email
     *         was attempted (e.g. row in non-sendable status).
     */
    public function getMessageType(): ?string
    {
        return $this->messageType;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function usedMockData(): bool
    {
        return $this->usedMockData;
    }

    public static function success(string $messageType, bool $usedMockData = false): self
    {
        return new self(true, $messageType, null, $usedMockData);
    }

    public static function failure(?string $messageType, string $errorMessage, bool $usedMockData = false): self
    {
        return new self(false, $messageType, $errorMessage, $usedMockData);
    }

    public static function notSendable(string $errorMessage): self
    {
        return new self(false, null, $errorMessage, false);
    }
}
