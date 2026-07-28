<?php

declare(strict_types=1);

namespace Nufat\Nutemplete;

/**
 * Block class
 * 
 * Represents a section block within Nutemplete.
 */
class Block
{
    protected ?string $name;
    protected string $content;
    protected bool $escaped;

    public function __construct(?string $name = null)
    {
        $this->name = $name;
        $this->content = "";
        $this->escaped = false;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): void
    {
        $this->name = $name;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function setContent(string $content): void
    {
        $this->content = $content;
    }

    public function append(string $content): void
    {
        $this->content .= $content;
    }

    public function prepend(string $content): void
    {
        $this->content = $content . $this->content;
    }

    public function escape(): string
    {
        if (!$this->escaped) {
            return htmlspecialchars($this->content, ENT_QUOTES, "UTF-8");
        }
        return $this->content;
    }

    public function e(): string
    {
        return $this->escape();
    }

    public function call(callable|string $function): mixed
    {
        if ($function instanceof \Closure || (is_string($function) && function_exists($function))) {
            return $function($this->content);
        }
        throw new \InvalidArgumentException("The function provided cannot be called on Block content.");
    }

    public function __toString(): string
    {
        return $this->content;
    }

    public function setEscaped(bool $escaped): void
    {
        $this->escaped = $escaped;
    }
}
