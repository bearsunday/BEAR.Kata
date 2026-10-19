<?php

declare(strict_types=1);

namespace BEAR\Kata\Renderer;

use BEAR\Csrf\Html\CsrfHiddenInput;
use Qiq\Helper\Html\HtmlHelpers;

/** Qiq's HTML helpers plus the ones the CMS templates need. */
final class CmsHelpers extends HtmlHelpers
{
    public function __construct(private readonly CsrfHiddenInput $csrfHiddenInput)
    {
        parent::__construct();
    }

    /** Already escaped: emit it raw, as `{{= csrfField() }}`. */
    public function csrfField(): string
    {
        return ($this->csrfHiddenInput)();
    }
}
