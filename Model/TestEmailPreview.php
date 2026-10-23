<?php
declare(strict_types=1);
namespace ReviewAutomation\Reviews\Model;

/** Public, sample-only screens: no customer token, data lookup or persistence. */
class TestEmailPreview
{
    public const STEPS = ['happy', 'unhappy', 'feedback', 'feedbackthanks', 'review', 'unsubscribe', 'preferences', 'complete'];

    public static function isValidStep(string $step): bool
    {
        return in_array($step, self::STEPS, true);
    }

    public static function getUrls(string $baseUrl): array
    {
        $base = rtrim($baseUrl, '/') . '/reviewautomation_reviews/preview/index/step/';
        return ['happy' => $base . 'happy', 'unhappy' => $base . 'unhappy',
            'review' => $base . 'review', 'unsubscribe' => $base . 'unsubscribe'];
    }
}
