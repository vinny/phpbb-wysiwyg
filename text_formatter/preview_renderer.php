<?php
/**
*
* @package extension vinny/wysiwyg
* @copyright (c) 2026 Vinny
* @license http://opensource.org/licenses/gpl-license.php GNU Public License
*
*/

namespace vinny\wysiwyg\text_formatter;

/**
* Lazy s9e renderer wrapper for custom BBCode previews.
* Avoids eager instantiation of phpBB's text_formatter.renderer during core.user_setup / ACP
* where $user->style['style_id'] is not yet initialized.
*/
class preview_renderer
{
	/** @var \phpbb\cache\driver\driver_interface */
	protected $cache;

	/** @var string */
	protected $cache_dir;

	/** @var string */
	protected $cache_key;

	/** @var \phpbb\textformatter\s9e\factory */
	protected $factory;

	/** @var \s9e\TextFormatter\Renderer|null */
	protected $s9e_renderer = null;

	/**
	* Constructor
	*
	* @param \phpbb\cache\driver\driver_interface $cache
	* @param string $cache_dir
	* @param string $cache_key
	* @param \phpbb\textformatter\s9e\factory $factory
	*/
	public function __construct($cache, $cache_dir, $cache_key, $factory)
	{
		$this->cache = $cache;
		$this->cache_dir = $cache_dir;
		$this->cache_key = $cache_key;
		$this->factory = $factory;
	}

	/**
	* Render an s9e XML fragment to HTML
	*
	* @param string $xml
	* @return string
	*/
	public function render($xml)
	{
		$renderer = $this->getS9eRenderer();
		if ($renderer === null)
		{
			return '';
		}

		return $renderer->render($xml);
	}

	/**
	* Lazily load phpBB's cached s9e\TextFormatter\Renderer instance
	*
	* @return \s9e\TextFormatter\Renderer|null
	*/
	protected function getS9eRenderer()
	{
		if ($this->s9e_renderer !== null)
		{
			return $this->s9e_renderer;
		}

		$renderer_data = ($this->cache !== null) ? $this->cache->get($this->cache_key) : false;
		if (is_array($renderer_data) && !empty($renderer_data['class']))
		{
			$class = $renderer_data['class'];
			if (!class_exists($class, false))
			{
				$cache_file = $this->cache_dir . $class . '.php';
				if (file_exists($cache_file))
				{
					include($cache_file);
				}
			}
			if (class_exists($class, false))
			{
				$this->s9e_renderer = new $class;
				return $this->s9e_renderer;
			}
		}

		if ($this->factory !== null && method_exists($this->factory, 'regenerate'))
		{
			$objects = $this->factory->regenerate();
			if (isset($objects['renderer']))
			{
				$this->s9e_renderer = $objects['renderer'];
				return $this->s9e_renderer;
			}
		}

		return null;
	}
}
