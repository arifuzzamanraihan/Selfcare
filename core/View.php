<?php
// Selfcare original code. Renders plain PHP templates inside a layout.
declare(strict_types=1);

namespace Selfcare;

final class View
{
    public function __construct(private string $templateDir)
    {
    }

    /**
     * Renders a template inside a layout. Pass 'layout' => 'dash/layout' to pick another layout.
     * @param array<string,mixed> $vars
     */
    public function page(string $template, array $vars = []): void
    {
        $layout = (string) ($vars['layout'] ?? 'layout');
        unset($vars['layout']);
        $content = $this->partial($template, $vars);
        $vars['content'] = $content;
        extract($vars, EXTR_SKIP);
        require $this->templateDir . '/' . $layout . '.php';
    }

    /** Renders a template and returns the HTML. @param array<string,mixed> $vars */
    public function partial(string $template, array $vars = []): string
    {
        extract($vars, EXTR_SKIP);
        ob_start();
        require $this->templateDir . '/' . $template . '.php';
        return (string) ob_get_clean();
    }
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}
