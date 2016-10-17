<?php

namespace Tndhl\LinkPreview\Models;

use Tndhl\LinkPreview\Contracts\PreviewInterface;
use Tndhl\LinkPreview\Traits\HasExportableFields;
use Tndhl\LinkPreview\Traits\HasImportableFields;

class HtmlPreview implements PreviewInterface
{
    use HasExportableFields;
    use HasImportableFields;

    /**
     * @var string $description Link description
     */
    private $description;

    /**
     * @var string $cover Cover image (usually chosen by webmaster)
     */
    private $cover;

    /**
     * @var array Images found while parsing the link
     */
    private $images = [];

    /**
     * @var string $title Link title
     */
    private $title;

    /**
     * Fields exposed
     * @var array
     */
    private $fields = [
        'cover',
        'images',
        'title',
        'description'
    ];
}
