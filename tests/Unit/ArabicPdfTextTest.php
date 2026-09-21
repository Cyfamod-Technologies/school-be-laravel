<?php

use App\Support\ArabicPdfText;

it('shapes and visually orders Arabic text for Dompdf', function () {
    $shaped = ArabicPdfText::forDompdf('مدرسة');

    expect($shaped)
        ->not->toBe('مدرسة')
        ->toMatch('/[\x{FB50}-\x{FDFF}\x{FE70}-\x{FEFF}]/u')
        ->and(ArabicPdfText::forDompdf('English'))->toBe('English');
});
