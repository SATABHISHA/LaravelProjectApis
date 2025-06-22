<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class NumberController extends Controller
{
    public function numberToWords(Request $request)
    {
        $request->validate([
            'number' => 'required'
        ]);

        $number = $request->input('number');

        if (!is_numeric($number)) {
            return response()->json([
                'status' => false,
                'message' => 'Please provide a valid numeric value.'
            ], 400);
        }

        $words = $this->convertNumberToWords($number);

        return response()->json([
            'status' => true,
            'number' => $number,
            'words' => 'Rupees ' . $words
        ]);
    }

    // Supports up to crores and millions
    private function convertNumberToWords($number)
    {
        $hyphen      = '-';
        $conjunction = ' and ';
        $separator   = ', ';
        $negative    = 'negative ';
        $decimal     = ' point ';
        $dictionary  = [
            0                   => 'zero',
            1                   => 'one',
            2                   => 'two',
            3                   => 'three',
            4                   => 'four',
            5                   => 'five',
            6                   => 'six',
            7                   => 'seven',
            8                   => 'eight',
            9                   => 'nine',
            10                  => 'ten',
            11                  => 'eleven',
            12                  => 'twelve',
            13                  => 'thirteen',
            14                  => 'fourteen',
            15                  => 'fifteen',
            16                  => 'sixteen',
            17                  => 'seventeen',
            18                  => 'eighteen',
            19                  => 'nineteen',
            20                  => 'twenty',
            30                  => 'thirty',
            40                  => 'forty',
            50                  => 'fifty',
            60                  => 'sixty',
            70                  => 'seventy',
            80                  => 'eighty',
            90                  => 'ninety',
            100                 => 'hundred',
            1000                => 'thousand',
            100000              => 'lakh',
            10000000            => 'crore',
            1000000000          => 'billion',
            1000000000000       => 'trillion',
        ];

        if (!is_numeric($number)) {
            return false;
        }

        if (($number >= 0 && (int) $number < 0) || (int) $number < 0 - PHP_INT_MAX) {
            // overflow
            trigger_error(
                'convertNumberToWords only accepts numbers between -' . PHP_INT_MAX . ' and ' . PHP_INT_MAX,
                E_USER_WARNING
            );
            return false;
        }

        if ($number < 0) {
            return $negative . $this->convertNumberToWords(abs($number));
        }

        $string = $fraction = null;

        if (strpos($number, '.') !== false) {
            list($number, $fraction) = explode('.', $number);
        }

        switch (true) {
            case $number < 21:
                $string = $dictionary[$number];
                break;
            case $number < 100:
                $tens   = ((int) ($number / 10)) * 10;
                $units  = $number % 10;
                $string = $dictionary[$tens];
                if ($units) {
                    $string .= $hyphen . $dictionary[$units];
                }
                break;
            case $number < 1000:
                $hundreds  = (int) ($number / 100);
                $remainder = $number % 100;
                $string = $dictionary[$hundreds] . ' ' . $dictionary[100];
                if ($remainder) {
                    $string .= $conjunction . $this->convertNumberToWords($remainder);
                }
                break;
            case $number < 100000:
                $thousands   = (int) ($number / 1000);
                $remainder = $number % 1000;
                $string = $this->convertNumberToWords($thousands) . ' ' . $dictionary[1000];
                if ($remainder) {
                    $string .= $separator . $this->convertNumberToWords($remainder);
                }
                break;
            case $number < 10000000:
                $lakhs   = (int) ($number / 100000);
                $remainder = $number % 100000;
                $string = $this->convertNumberToWords($lakhs) . ' ' . $dictionary[100000];
                if ($remainder) {
                    $string .= $separator . $this->convertNumberToWords($remainder);
                }
                break;
            case $number < 1000000000:
                $crores   = (int) ($number / 10000000);
                $remainder = $number % 10000000;
                $string = $this->convertNumberToWords($crores) . ' ' . $dictionary[10000000];
                if ($remainder) {
                    $string .= $separator . $this->convertNumberToWords($remainder);
                }
                break;
            default:
                $billions   = (int) ($number / 1000000000);
                $remainder = $number % 1000000000;
                $string = $this->convertNumberToWords($billions) . ' ' . $dictionary[1000000000];
                if ($remainder) {
                    $string .= $separator . $this->convertNumberToWords($remainder);
                }
                break;
        }

        if (null !== $fraction && is_numeric($fraction)) {
            $string .= $decimal;
            $words = [];
            foreach (str_split((string) $fraction) as $number) {
                $words[] = $dictionary[$number];
            }
            $string .= implode(' ', $words);
        }

        return $string;
    }
}
