<?php

declare(strict_types=1);

namespace biometric\src\core\biometrics;

final class FaceDescriptorMatcher
{
    public const DESCRIPTOR_LENGTH = 128;
    public const ACCEPT_THRESHOLD = 0.42;
    public const REVIEW_THRESHOLD = 0.50;
    public const MARGIN_THRESHOLD = 0.035;

    public static function normalizeDescriptor($descriptor): ?array
    {
        if (!is_array($descriptor) || count($descriptor) !== self::DESCRIPTOR_LENGTH) {
            return null;
        }

        $normalized = [];
        foreach ($descriptor as $value) {
            if (!is_numeric($value)) {
                return null;
            }

            $number = (float) $value;
            if (!is_finite($number) || abs($number) > 10) {
                return null;
            }
            $normalized[] = $number;
        }

        return $normalized;
    }

    public static function match(array $probe, array $candidates): array
    {
        $normalizedProbe = self::normalizeDescriptor($probe);
        if ($normalizedProbe === null) {
            throw new \InvalidArgumentException('Invalid face descriptor');
        }

        $best = null;
        $second = null;
        $candidateCount = 0;

        foreach ($candidates as $candidate) {
            $candidate = is_object($candidate) ? get_object_vars($candidate) : $candidate;
            if (!is_array($candidate)) {
                continue;
            }

            $personId = trim((string) ($candidate['person_id'] ?? $candidate['personId'] ?? ''));
            $encoding = self::normalizeDescriptor($candidate['encoding'] ?? null);
            if ($personId === '' || $encoding === null) {
                continue;
            }

            $sum = 0.0;
            foreach ($normalizedProbe as $index => $value) {
                $difference = $value - $encoding[$index];
                $sum += $difference * $difference;
            }

            $rankedCandidate = [
                'person_id' => $personId,
                'distance' => sqrt($sum),
            ];
            $candidateCount++;

            if ($best === null || $rankedCandidate['distance'] < $best['distance']) {
                $second = $best;
                $best = $rankedCandidate;
            } elseif ($second === null || $rankedCandidate['distance'] < $second['distance']) {
                $second = $rankedCandidate;
            }
        }

        if ($best === null) {
            return self::result('rejected', null, null, null, null, 0.0, $candidateCount);
        }

        $margin = $second !== null
            ? $second['distance'] - $best['distance']
            : INF;
        $confidence = self::clamp(
            1.0 - (($best['distance'] - self::ACCEPT_THRESHOLD)
                / (self::REVIEW_THRESHOLD - self::ACCEPT_THRESHOLD))
        );

        if (
            $best['distance'] <= self::ACCEPT_THRESHOLD
            && $margin >= self::MARGIN_THRESHOLD
        ) {
            return self::result(
                'accepted',
                $best['person_id'],
                $best['distance'],
                $second['distance'] ?? null,
                $margin,
                $confidence,
                $candidateCount
            );
        }

        if ($best['distance'] <= self::REVIEW_THRESHOLD) {
            return self::result(
                'review',
                null,
                $best['distance'],
                $second['distance'] ?? null,
                $margin,
                $confidence,
                $candidateCount
            );
        }

        return self::result(
            'rejected',
            null,
            $best['distance'],
            $second['distance'] ?? null,
            $margin,
            0.0,
            $candidateCount
        );
    }

    private static function result(
        string $state,
        ?string $personId,
        ?float $bestDistance,
        ?float $secondBestDistance,
        ?float $margin,
        float $confidence,
        int $candidateCount
    ): array {
        return [
            'state' => $state,
            'person_id' => $personId,
            'best_distance' => self::roundMetric($bestDistance),
            'second_best_distance' => self::roundMetric($secondBestDistance),
            'margin' => is_infinite((float) $margin) ? null : self::roundMetric($margin),
            'confidence' => round(self::clamp($confidence), 4),
            'candidate_count' => $candidateCount,
        ];
    }

    private static function roundMetric(?float $value): ?float
    {
        return $value === null || !is_finite($value) ? null : round($value, 6);
    }

    private static function clamp(float $value): float
    {
        return max(0.0, min(1.0, $value));
    }
}
