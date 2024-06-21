<?php

namespace Nufat\Nutemplete;

use Nufat\Nutemplete\Block;
use Nufat\Nutemplete\Render;

class Template implements \ArrayAccess
{
	protected $templatePath;
	protected $environment;
	protected $content;
	private $stack = array();
	protected $blocks = array();
	protected $extends = null;

	public function __construct($path = null)
	{
		$this->templatePath = $path;
		$this->environment = null;
		$this->content = new Block();
	}

	public static function withEnvironment(Render $environment, $path)
	{
		$obj = ($path === null) ? new self(null) : new self($environment->getTemplatePath($path));
		$obj->setEnvironment($environment);
		return $obj;
	}
	public function extend($path)
	{
		if ($path === null) {
			return;
		} else if ($this->environment !== null) {
			if ($this->templatePath == $this->environment->getTemplatePath($path))
				return;
			$this->extends = Template::withEnvironment($this->environment, $path);
		} else if ($this->templatePath != $path) {
			$this->extends = new Template($path);
		}
	}

	public function block($name = null, $value = null)
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
			foreach ($this->stack as &$b)
				$b->append($content);
		}

		ob_start();
		$block = new Block($name);
		array_push($this->stack, $block);
	}

	public function endblock(\Closure $filter = null)
	{
		$content = ob_get_clean();
		foreach ($this->stack as &$b)
			$b->append($content);
		$block = array_pop($this->stack);

		if ($filter !== null) {
			$block->setContent($filter($block->getContent()));
		}

		if (($name = $block->getName()) != null)
			$this->blocks[$block->getName()] = $block;
		return $block;
	}

	public function getBlocks()
	{
		if (!$this['content'])
			$this['content'] = $this->content;
		else
			$this['content'] = $this['content'] . $this->content;
		return $this->blocks;
	}

	public function setBlocks(array $blocks)
	{
		$this->blocks = $blocks;
	}

	protected function renderComponents($content, $variables)
	{
		// Adjust the regex to capture multiple attributes and make data attribute optional
		$pattern = '/<nu-([\w-]+)([^>]*)>(.*?)<\/nu-\1>/s';

		$content = preg_replace_callback($pattern, function ($matches) use ($variables) {
			$component = $matches[1];
			$attributes = $matches[2];
			$slotContent = $matches[3]; // Get the content between tags

			// Extract all attributes
			preg_match_all('/([\w-]+)\s*=\s*([\'"])(.*?)\2/', $attributes, $attributeMatches, PREG_SET_ORDER);
			$data = [];
			foreach ($attributeMatches as $attr) {
				$attrName = $attr[1];
				$attrValue = $attr[3];
				if ($attrName === 'data') {
					$data = json_decode($attrValue, true);
					if (json_last_error() !== JSON_ERROR_NONE) {
						throw new \InvalidArgumentException('Invalid JSON data: ' . $attrValue);
					}
				} else {
					$data[$attrName] = $attrValue;
				}
			}

			// Check if the component file exists in a subfolder
			$componentPath = $this->findComponent($component);

			if ($componentPath !== null) {
				// Merge the component data with the global variables
				$mergedVariables = array_merge($variables, $data, ['slot' => $slotContent]); // Include the content as a variable

				// Render the component with merged variables
				return $this->Component($component, $mergedVariables);
			} else {
				throw new \InvalidArgumentException("Component file could not be found.");
			}
		}, $content);

		return $content;
	}


	public function render(array $variables = array())
	{
		if ($this->templatePath !== null) {
			$_file = $this->templatePath;

			if (!file_exists($_file))
				throw new \InvalidArgumentException(sprintf("Could not render. The file %s could not be found", $_file));

			extract($variables, EXTR_SKIP);
			$content = file_get_contents($_file);

			// Step 1: Replace Blade syntax with PHP code
			$content = $this->replaceBladeSyntax($content, $variables);

			// Step 2: Evaluate the PHP code
			ob_start();
			eval('?>' . $content);
			$evaluatedContent = ob_get_clean();

			// Step 3: Render the components in the evaluated content
			$evaluatedContent = $this->renderComponents($evaluatedContent, $variables);

			// Append the final content
			$this->content->append($evaluatedContent);
		}

		if ($this->extends !== null) {
			$this->extends->setBlocks($this->getBlocks());
			$content = (string)$this->extends->render();
			return $content;
		}

		return (string)$this->content;
	}

	public function setEnvironment(Render $environment)
	{
		$this->environment = $environment;
	}

	public function __isset($id)
	{
		return isset($this->environment->$id);
	}

	public function __get($id)
	{
		return $this->environment->$id;
	}

	public function __set($id, $value)
	{
		$this->environment->$id = $value;
	}

	public function offsetExists($offset): bool
	{
		return isset($this->blocks[$offset]);
	}

	public function offsetGet($offset): mixed
	{
		return $this->blocks[$offset] ?? false;
	}

	public function offsetSet($offset, $value): void
	{
		if (isset($this->blocks[$offset])) {
			$this->blocks[$offset]->setContent((string)$value);
		} else {
			$block = new Block($offset);
			$block->setContent((string)$value);
			$this->blocks[$offset] = $block;
		}
	}

	public function offsetUnset($offset): void
	{
		unset($this->blocks[$offset]);
	}


	public function gComponent($component, array $variables = array())
	{
		// Generate the full path to the component file
		$componentPath = $this->environment->getTemplateDir() . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . $component . '.nu.php';

		// Check if the component file exists
		if (!file_exists($componentPath)) {
			throw new \InvalidArgumentException(sprintf("Component file %s could not be found", $componentPath));
		}

		extract($variables, EXTR_SKIP);
		ob_start();
		require($componentPath);
		return ob_get_clean();
	}
	public function ComponentView($component, array $variables = array())
	{
		// Generate the full path to the component file
		$componentPath = 'views' . DIRECTORY_SEPARATOR . 'components' . DIRECTORY_SEPARATOR . $component . '.nu.php';

		// Check if the component file exists
		if (!file_exists($componentPath)) {
			throw new \InvalidArgumentException(sprintf("Component file %s could not be found", $componentPath));
		}

		extract($variables, EXTR_SKIP);
		ob_start();
		require($componentPath);
		return ob_get_clean();
	}

	protected function Component($component, array $variables = array())
	{
		// Generate the full path to the component file
		$componentPath = $this->findComponent($component);

		// Check if the component file exists
		if ($componentPath !== null) {
			extract($variables, EXTR_SKIP); // Extract variables for use in the component file
			$content = $this->replaceBladeSyntax(file_get_contents($componentPath), $variables); // Get file content and apply Blade syntax
			ob_start();
			eval('?>' . $content); // Evaluate the PHP content
			return ob_get_clean();
		} else {
			throw new \InvalidArgumentException(sprintf("Component file %s could not be found", $component));
		}
	}


	protected function findComponent($component)
	{
		$templateDir = $this->environment->getTemplateDir();
		// Define the base directory for components relative to the template directory
		$baseDir = $templateDir . '/../resource/components';

		// Check if the component exists in a subfolder
		$componentPath = $baseDir . DIRECTORY_SEPARATOR . str_replace('-', DIRECTORY_SEPARATOR, $component) . '.nu.php';

		if (file_exists($componentPath)) {
			return $componentPath;
		} else {
			return null;
		}
	}

	protected function replaceBladeSyntax($content, $variables)
	{
		// Patterns to match Blade-like syntax {{ $variable }} and {{ variable }}
		$patterns = [
			'/{{\s*\$(.*?)\s*}}/', // with $
			'/{{\s*(.*?)\s*}}/'    // without $
		];

		// Callback function to replace matched patterns with PHP code
		foreach ($patterns as $pattern) {
			$content = preg_replace_callback($pattern, function ($matches) use ($variables) {
				$keys = explode('.', $matches[1]);
				$value = $this->getValueFromArray($variables, $keys);

				if ($value !== null) {
					return '<?php echo htmlspecialchars(' . var_export($value, true) . ', ENT_QUOTES, "UTF-8"); ?>';
				} else {
					return $matches[0]; // Leave unchanged if variable not found
				}
			}, $content);
		}

		return $content;
	}

	protected function getValueFromArray($array, $keys)
	{
		foreach ($keys as $key) {
			if (is_array($array) && array_key_exists($key, $array)) {
				$array = $array[$key];
			} else {
				return null;
			}
		}
		return $array;
	}
}
