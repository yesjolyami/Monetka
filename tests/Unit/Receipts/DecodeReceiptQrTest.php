<?php

use App\Actions\Receipts\DecodeReceiptQr;
use Illuminate\Http\UploadedFile;

function receiptPhoto(string $name): UploadedFile
{
    $path = dirname(__DIR__, 2).'/Fixtures/receipts/'.$name;

    return new UploadedFile($path, $name, mime_content_type($path) ?: null, null, true);
}

it('reads a fiscal qr from a photo', function () {
    $text = (new DecodeReceiptQr)->execute(receiptPhoto('fiscal-qr.png'));

    expect($text)->toContain('fn=9285000100206366')
        ->and($text)->toContain('fp=3951774668');
});

it('reads a fiscal qr placed on a large receipt photo', function () {
    $text = (new DecodeReceiptQr)->execute(receiptPhoto('fiscal-qr-on-receipt.jpg'));

    expect($text)->toContain('fn=9285000100206366');
});
