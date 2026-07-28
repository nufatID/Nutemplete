<?php

declare(strict_types=1);

namespace Nufat\Nutemplete;

class Render
{
    private string $templateDir;
    private string $extension;
    private array $variables = [];
    public ?Template $layout = null;

    /**
     * Constructor
     */
    public function __construct(string $templateDir, string $extension = '')
    {
        $this->templateDir = rtrim($templateDir, '/\\');
        $this->extension = $extension;
    }

    /**
     * Render a template file
     */
    public function render(string $path, array $variables = []): string
    {
        $template = Template::withEnvironment($this, $path);
        return $template->render(array_merge($this->variables, $variables));
    }

    /**
     * Creates an empty template in this environment
     */
    public function template(): Template
    {
        return Template::withEnvironment($this, null);
    }

    /**
     * Gets the full path of the template
     */
    public function getTemplatePath(string $template): string
    {
        $path = $template;
        if (!empty($this->extension) && !str_ends_with($path, $this->extension)) {
            $path .= $this->extension;
        }
        return $this->getTemplateDir() . DIRECTORY_SEPARATOR . ltrim($path, '/\\');
    }

    public function __isset(string $id): bool
    {
        return isset($this->variables[$id]);
    }

    public function __get(string $id): mixed
    {
        return $this->variables[$id] ?? null;
    }

    public function __set(string $id, mixed $value): void
    {
        $this->variables[$id] = $value;
    }

    public function getTemplateDir(): string
    {
        return $this->templateDir;
    }

    public function setTemplateDir(string $templateDir): void
    {
        $this->templateDir = rtrim($templateDir, '/\\');
    }

    public function getExtension(): string
    {
        return $this->extension;
    }

    public function setExtension(string $extension): void
    {
        $this->extension = $extension;
    }
}
