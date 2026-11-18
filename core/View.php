<?php
// Selfcare original code. Renders plain PHP templates inside a layout.
declare(strict_types=1);

namespace Selfcare;

final class View
{
    public function __construct(private string $templateDir)
    {
    }

    /** @param array<string,mixed> $data */
    public function page(string $template, array $data = []): void
    {
        extract($data, EXTR_SKIP);
        ob_start();
        require $this->templateDir . '/' . $template . '.php';
        $content = (string) ob_get_clean();
        require $this->templateDir . '/layout.php';
    }
}

function e(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES, 'UTF-8');
}
