<?php

declare(strict_types=1);

namespace UltimaVox\Modules\Documents\Admin;

use RuntimeException;

final class DocumentConflictException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Документ изменён после открытия формы или форма устарела. Ваш текст сохранён в этой форме. Откройте актуальную версию в новой вкладке и перенесите нужные изменения.');
    }
}
