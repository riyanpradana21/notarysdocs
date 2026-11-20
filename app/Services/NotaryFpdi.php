<?php

namespace App\Services;

use setasign\Fpdi\Fpdi;

class NotaryFpdi extends Fpdi
{
    /**
     * Set native PDF line dash pattern.
     *
     * In the PDF specification (ISO 32000), dashed lines are produced natively
     * with the "[dash_length gap_length] 0 d" operator. Using this native operator
     * renders instantly with zero CPU overhead and produces lightweight PDF files,
     * unlike manual segment loops which can generate tens of thousands of individual paths.
     *
     * @param  float|null  $black  Dash length in points (pass null to reset to solid line)
     * @param  float|null  $white  Gap length in points
     */
    public function setDash(?float $black = null, ?float $white = null): void
    {
        if ($black !== null && $white !== null && $black > 0 && $white > 0) {
            $s = sprintf('[%.3F %.3F] 0 d', $black, $white);
        } else {
            $s = '[] 0 d';
        }

        $this->_out($s);
    }
}
