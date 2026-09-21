<?php

namespace App\Support;

final class ArabicPdfText
{
    /**
     * Arabic presentation forms ordered as isolated, final, initial, medial.
     * Null entries indicate that the letter cannot join in that direction.
     *
     * @var array<string, array{0: string, 1: string|null, 2: string|null, 3: string|null}>
     */
    private const FORMS = [
        "\u{0621}" => ["\u{FE80}", null, null, null],
        "\u{0622}" => ["\u{FE81}", "\u{FE82}", null, null],
        "\u{0623}" => ["\u{FE83}", "\u{FE84}", null, null],
        "\u{0624}" => ["\u{FE85}", "\u{FE86}", null, null],
        "\u{0625}" => ["\u{FE87}", "\u{FE88}", null, null],
        "\u{0626}" => ["\u{FE89}", "\u{FE8A}", "\u{FE8B}", "\u{FE8C}"],
        "\u{0627}" => ["\u{FE8D}", "\u{FE8E}", null, null],
        "\u{0628}" => ["\u{FE8F}", "\u{FE90}", "\u{FE91}", "\u{FE92}"],
        "\u{0629}" => ["\u{FE93}", "\u{FE94}", null, null],
        "\u{062A}" => ["\u{FE95}", "\u{FE96}", "\u{FE97}", "\u{FE98}"],
        "\u{062B}" => ["\u{FE99}", "\u{FE9A}", "\u{FE9B}", "\u{FE9C}"],
        "\u{062C}" => ["\u{FE9D}", "\u{FE9E}", "\u{FE9F}", "\u{FEA0}"],
        "\u{062D}" => ["\u{FEA1}", "\u{FEA2}", "\u{FEA3}", "\u{FEA4}"],
        "\u{062E}" => ["\u{FEA5}", "\u{FEA6}", "\u{FEA7}", "\u{FEA8}"],
        "\u{062F}" => ["\u{FEA9}", "\u{FEAA}", null, null],
        "\u{0630}" => ["\u{FEAB}", "\u{FEAC}", null, null],
        "\u{0631}" => ["\u{FEAD}", "\u{FEAE}", null, null],
        "\u{0632}" => ["\u{FEAF}", "\u{FEB0}", null, null],
        "\u{0633}" => ["\u{FEB1}", "\u{FEB2}", "\u{FEB3}", "\u{FEB4}"],
        "\u{0634}" => ["\u{FEB5}", "\u{FEB6}", "\u{FEB7}", "\u{FEB8}"],
        "\u{0635}" => ["\u{FEB9}", "\u{FEBA}", "\u{FEBB}", "\u{FEBC}"],
        "\u{0636}" => ["\u{FEBD}", "\u{FEBE}", "\u{FEBF}", "\u{FEC0}"],
        "\u{0637}" => ["\u{FEC1}", "\u{FEC2}", "\u{FEC3}", "\u{FEC4}"],
        "\u{0638}" => ["\u{FEC5}", "\u{FEC6}", "\u{FEC7}", "\u{FEC8}"],
        "\u{0639}" => ["\u{FEC9}", "\u{FECA}", "\u{FECB}", "\u{FECC}"],
        "\u{063A}" => ["\u{FECD}", "\u{FECE}", "\u{FECF}", "\u{FED0}"],
        "\u{0641}" => ["\u{FED1}", "\u{FED2}", "\u{FED3}", "\u{FED4}"],
        "\u{0642}" => ["\u{FED5}", "\u{FED6}", "\u{FED7}", "\u{FED8}"],
        "\u{0643}" => ["\u{FED9}", "\u{FEDA}", "\u{FEDB}", "\u{FEDC}"],
        "\u{0644}" => ["\u{FEDD}", "\u{FEDE}", "\u{FEDF}", "\u{FEE0}"],
        "\u{0645}" => ["\u{FEE1}", "\u{FEE2}", "\u{FEE3}", "\u{FEE4}"],
        "\u{0646}" => ["\u{FEE5}", "\u{FEE6}", "\u{FEE7}", "\u{FEE8}"],
        "\u{0647}" => ["\u{FEE9}", "\u{FEEA}", "\u{FEEB}", "\u{FEEC}"],
        "\u{0648}" => ["\u{FEED}", "\u{FEEE}", null, null],
        "\u{0649}" => ["\u{FEEF}", "\u{FEF0}", null, null],
        "\u{064A}" => ["\u{FEF1}", "\u{FEF2}", "\u{FEF3}", "\u{FEF4}"],
        "\u{0671}" => ["\u{FB50}", "\u{FB51}", null, null],
        "\u{067E}" => ["\u{FB56}", "\u{FB57}", "\u{FB58}", "\u{FB59}"],
        "\u{0686}" => ["\u{FB7A}", "\u{FB7B}", "\u{FB7C}", "\u{FB7D}"],
        "\u{0698}" => ["\u{FB8A}", "\u{FB8B}", null, null],
        "\u{06A9}" => ["\u{FB8E}", "\u{FB8F}", "\u{FB90}", "\u{FB91}"],
        "\u{06AF}" => ["\u{FB92}", "\u{FB93}", "\u{FB94}", "\u{FB95}"],
        "\u{06CC}" => ["\u{FBFC}", "\u{FBFD}", "\u{FBFE}", "\u{FBFF}"],
    ];

    /**
     * Shape and visually order Arabic for PDF renderers without Arabic shaping.
     */
    public static function forDompdf(string $text): string
    {
        if (preg_match('/[\x{0600}-\x{06FF}]/u', $text) !== 1) {
            return $text;
        }

        $characters = preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY);

        if ($characters === false || $characters === []) {
            return $text;
        }

        $shaped = [];

        foreach ($characters as $index => $character) {
            $forms = self::FORMS[$character] ?? null;

            if ($forms === null) {
                $shaped[] = $character;
                continue;
            }

            $previousForms = self::nearestLetterForms($characters, $index, -1);
            $nextForms = self::nearestLetterForms($characters, $index, 1);
            $joinsPrevious = $forms[1] !== null && $previousForms !== null && $previousForms[2] !== null;
            $joinsNext = $forms[2] !== null && $nextForms !== null && $nextForms[1] !== null;

            $shaped[] = match (true) {
                $joinsPrevious && $joinsNext => $forms[3],
                $joinsPrevious => $forms[1],
                $joinsNext => $forms[2],
                default => $forms[0],
            };
        }

        return implode('', array_reverse($shaped));
    }

    /** @return array{0: string, 1: string|null, 2: string|null, 3: string|null}|null */
    private static function nearestLetterForms(array $characters, int $index, int $direction): ?array
    {
        $candidateIndex = $index + $direction;

        while (isset($characters[$candidateIndex]) && preg_match('/[\x{064B}-\x{065F}\x{0670}]/u', $characters[$candidateIndex])) {
            $candidateIndex += $direction;
        }

        return self::FORMS[$characters[$candidateIndex] ?? ''] ?? null;
    }
}
