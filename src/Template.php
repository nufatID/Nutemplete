<?php

declare(strict_types=1);

namespace Nufat\Nutemplete;

class Template implements \ArrayAccess
{
    protected ?string $templatePath = null;
    protected ?Render $environment = null;
    protected Block $content;
    private array $stack = [];
    protected array $blocks = [];
    protected ?Template $extends = null;
    protected BladeSyntax $bladeSyntax;

    public function __construct(?string $path = null)
    {
        $this->templatePath = $path;
        $this->content = new Block();
        $this->bladeSyntax = new BladeSyntax();
    }

    public static function withEnvironment(Render $environment, ?string $path): self
    {
        $templatePath = ($path === null) ? null : $environment->getTemplatePath($path);
        $obj = new self($templatePath);
        $obj->setEnvironment($environment);
        $obj->bladeSyntax = new BladeSyntax($environment);
        return $obj;
    }

    public function extend(?string $path): void
    {
        if ($path === null) {
            return;
        }

        if ($this->environment !== null) {
            $targetPath = $this->environment->getTemplatePath($path);
            if ($this->templatePath === $targetPath) {
                return;
            }
            $this->extends = self::withEnvironment($this->environment, $path);
        } else if ($this->templatePath !== $path) {
            $this->extends = new self($path);
        }
    }

    public function block(?string $name = null, ?string $value = null): void
    {
        if ($value !== null) {
            if ($name !== null) {
                $block = new Block($name);
                $block->setContent($value);
                $this->blocks[$name] = $block;
            } else {
                throw new \LogicException(sprintf("You are assigning a value of %s to a block with no name!", $value));
            }
            return;
        }

        if (!empty($this->stack)) {
            $content = ob_get_contents();
            if ($content !== false) {
                foreach ($this->stack as &$b) {
                    $b->append($content);
                }
            }
        }

        ob_start();
        $block = new Block($name);
        array_push($this->stack, $block);
    }

    public function endblock(?\Closure $filter = null): Block
    {
        $content = ob_get_clean();
        if ($content === false) {
            $content = '';
        }

        foreach ($this->stack as &$b) {
            $b->append($content);
        }
        $block = array_pop($this->stack) ?? new Block();

        if ($filter !== null) {
            $block->setContent($filter($block->getContent()));
        }

        if (($name = $block->getName()) !== null) {
            $this->blocks[$name] = $block;
        }
        return $block;
    }

    public function getBlocks(): array
    {
        if (!isset($this['content'])) {
            $this['content'] = (string)$this->content;
        } else {
            $this['content'] = $this['content'] . $this->content;
        }
        return $this->blocks;
    }

    public function setBlocks(array $blocks): void
    {
        $this->blocks = $blocks;
    }

    public function renderComponents(string $content, array $variables = []): string
    {
        $pattern = '/<nu-([\w-]+)([^>]*)>(.*?)<\/nu-\1>/s';

        return preg_replace_callback($pattern, function ($matches) use ($variables) {
            $component = $matches[1];
            $attributes = $matches[2];
            $slotContent = $matches[3];

            preg_match_all('/([\w-]+)\s*=\s*([\'"])(.*?)\2/', $attributes, $attributeMatches, PREG_SET_ORDER);
            $data = [];
            foreach ($attributeMatches as $attr) {
                $attrName = $attr[1];
                $attrValue = $attr[3];
                if ($attrName === 'data') {
                    $decoded = json_decode($attrValue, true);
                    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                        $data = array_merge($data, $decoded);
                    }
                } else {
                    $data[$attrName] = $attrValue;
                }
            }

            $componentPath = $this->findComponent($component);

            if ($componentPath !== null) {
                $mergedVariables = array_merge($variables, $data, ['slot' => $slotContent]);
                $renderedComponent = $this->renderComponentFile($componentPath, $mergedVariables);
                return $this->renderComponents($renderedComponent, $variables);
            }

            return $matches[0];
        }, $content) ?? $content;
    }

    public function render(array $variables = []): string
    {
        if ($this->templatePath !== null) {
            $_file = $this->templatePath;

            if (!file_exists($_file)) {
                throw new \InvalidArgumentException(sprintf("Could not render. The file %s could not be found", $_file));
            }

            extract($variables, EXTR_SKIP);
            $fileContent = file_get_contents($_file);
            if ($fileContent === false) {
                $fileContent = '';
            }

            $compiledCode = $this->bladeSyntax->replaceBladeSyntax($fileContent, $variables);

            ob_start();
            try {
                eval('?>' . $compiledCode);
                $evaluatedContent = ob_get_clean();
            } catch (\Throwable $e) {
                ob_end_clean();
                throw new \RuntimeException(sprintf("Template evaluation error in file %s: %s", $_file, $e->getMessage()), 0, $e);
            }

            if ($evaluatedContent !== false) {
                $evaluatedContent = $this->renderComponents($evaluatedContent, $variables);
                $this->content->append($evaluatedContent);
            }
        }

        if ($this->extends !== null) {
            $this->extends->setBlocks($this->getBlocks());
            return (string)$this->extends->render($variables);
        }

        return (string)$this->content;
    }

    public function setEnvironment(Render $environment): void
    {
        $this->environment = $environment;
    }

    public function __isset(string $id): bool
    {
        return isset($this->environment->$id);
    }

    public function __get(string $id): mixed
    {
        return $this->environment->$id ?? null;
    }

    public function __set(string $id, mixed $value): void
    {
        if ($this->environment !== null) {
            $this->environment->$id = $value;
        }
    }

    public function offsetExists(mixed $offset): bool
    {
        return isset($this->blocks[$offset]);
    }

    public function offsetGet(mixed $offset): mixed
    {
        return $this->blocks[$offset] ?? false;
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        if (isset($this->blocks[$offset])) {
            $this->blocks[$offset]->setContent((string)$value);
        } else {
            $block = new Block((string)$offset);
            $block->setContent((string)$value);
            $this->blocks[(string)$offset] = $block;
        }
    }

    public function offsetUnset(mixed $offset): void
    {
        unset($this->blocks[$offset]);
    }

    public function ComponentView(string $component, array $variables = []): string
    {
        $componentPath = 'views' . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . $component . '.nu.php';

        if (!file_exists($componentPath)) {
            throw new \InvalidArgumentException(sprintf("Component file %s could not be found", $componentPath));
        }

        return $this->renderComponentFile($componentPath, $variables);
    }

    protected function renderComponentFile(string $filePath, array $variables = []): string
    {
        extract($variables, EXTR_SKIP);
        $fileContent = file_get_contents($filePath);
        if ($fileContent === false) {
            return '';
        }

        $compiled = $this->bladeSyntax->replaceBladeSyntax($fileContent, $variables);
        ob_start();
        try {
            eval('?>' . $compiled);
            return ob_get_clean() ?: '';
        } catch (\Throwable $e) {
            ob_end_clean();
            throw new \RuntimeException(sprintf("Error rendering component %s: %s", $filePath, $e->getMessage()), 0, $e);
        }
    }

    protected function findComponent(string $component): ?string
    {
        if ($this->environment === null) {
            return null;
        }

        $templateDir = $this->environment->getTemplateDir();
        $baseDir = $templateDir . '/../resource/components';

        $componentPath = $baseDir . DIRECTORY_SEPARATOR . str_replace('-', DIRECTORY_SEPARATOR, $component) . '.nu.php';

        if (file_exists($componentPath)) {
            return $componentPath;
        }

        return null;
    }

    public function Qrcode(string $text): string
    {
        if (class_exists('Nufat\Nutemplete\NuQrcode')) {
            $qrcode = new NuQrcode();
            ob_start();
            $qrcode->qrcode($text);
            return ob_get_clean() ?: '';
        }
        return '';
    }
}
