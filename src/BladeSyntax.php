<?php

declare(strict_types=1);

namespace Nufat\Nutemplete;

class BladeSyntax
{
    protected mixed $environment;

    public function __construct(mixed $environment = null)
    {
        $this->environment = $environment;
    }

    public function replaceBladeSyntax(string $content, array $variables = []): string
    {
        // 1. Comments {{-- ... --}}
        $content = preg_replace('/{{\s*--(.*?)--\s*}}/s', '<?php /* $1 */ ?>', $content);

        // 2. Extends & Sections
        $content = $this->replaceExtendSyntax($content);
        $content = $this->replaceSectionSyntax($content);

        // 3. Unescaped Output {!! ... !!}
        $content = preg_replace('/{!!\s*(.*?)\s*!!}/s', '<?php echo $1; ?>', $content);

        // 4. Escaped Output {{ ... }}
        $content = preg_replace('/{{\s*(.*?)\s*}}/s', '<?php echo htmlspecialchars((string)($1 ?? ""), ENT_QUOTES, "UTF-8"); ?>', $content);

        // 5. Conditionals & Loops
        $content = $this->replaceConditionals($content);
        $content = $this->replaceLoopSyntax($content);
        $content = $this->replaceCustomDirectives($content);

        return $content;
    }

    protected function replaceSectionSyntax(string $content): string
    {
        // @section('name')
        $content = preg_replace('/@section\(["\'](.+?)["\']\)/', '<?php $this->block("$1"); ?>', $content);
        
        // @endsection / @stop
        $content = preg_replace('/@(endsection|stop)/', '<?php $this->endblock(); ?>', $content);

        // @yield('name', 'default')
        $content = preg_replace_callback('/@yield\(["\'](.+?)["\'](?:\s*,\s*(.+?))?\)/', function ($matches) {
            $name = $matches[1];
            $default = $matches[2] ?? "''";
            return '<?php echo $this["' . $name . '"] ?? ' . $default . '; ?>';
        }, $content);

        return $content;
    }

    protected function replaceExtendSyntax(string $content): string
    {
        return preg_replace_callback('/@extends\(["\'](.+?)["\']\)/', function ($matches) {
            $path = $matches[1];
            if (!str_ends_with($path, '.nu.php') && !str_ends_with($path, '.php')) {
                $path = str_replace('.', DIRECTORY_SEPARATOR, $path) . '.nu.php';
            }
            return '<?php $this->extend("' . $path . '"); ?>';
        }, $content);
    }

    protected function replaceConditionals(string $content): string
    {
        $content = preg_replace('/@if\s*\((.*?)\)/', '<?php if($1): ?>', $content);
        $content = preg_replace('/@elseif\s*\((.*?)\)/', '<?php elseif($1): ?>', $content);
        $content = preg_replace('/@else/', '<?php else: ?>', $content);
        $content = preg_replace('/@endif/', '<?php endif; ?>', $content);

        $content = preg_replace('/@unless\s*\((.*?)\)/', '<?php if(!($1)): ?>', $content);
        $content = preg_replace('/@endunless/', '<?php endif; ?>', $content);

        $content = preg_replace('/@isset\s*\((.*?)\)/', '<?php if(isset($1)): ?>', $content);
        $content = preg_replace('/@endisset/', '<?php endif; ?>', $content);

        $content = preg_replace('/@empty\s*\((.*?)\)/', '<?php if(empty($1)): ?>', $content);
        $content = preg_replace('/@endempty/', '<?php endif; ?>', $content);

        return $content;
    }

    protected function replaceLoopSyntax(string $content): string
    {
        $content = preg_replace('/@foreach\s*\((.*?)\)/', '<?php foreach($1): ?>', $content);
        $content = preg_replace('/@endforeach/', '<?php endforeach; ?>', $content);

        $content = preg_replace('/@for\s*\((.*?)\)/', '<?php for($1): ?>', $content);
        $content = preg_replace('/@endfor/', '<?php endfor; ?>', $content);

        $content = preg_replace('/@while\s*\((.*?)\)/', '<?php while($1): ?>', $content);
        $content = preg_replace('/@endwhile/', '<?php endwhile; ?>', $content);

        return $content;
    }

    protected function replaceCustomDirectives(string $content): string
    {
        $content = preg_replace('/@islogin/', '<?php if (function_exists("isLogin") && isLogin()): ?>', $content);
        $content = preg_replace('/@endislogin/', '<?php endif; ?>', $content);

        return $content;
    }
}
