<?

defined('C5_EXECUTE') or die("Access Denied.");
class Concrete5_Library_View extends AbstractView {

	protected $viewPath;
	protected $innerContentFile;
	protected $themeHandle;
	protected $themeObject;
	protected $themeRelativePath;
	protected $themeAbsolutePath;
	protected $themePkgHandle;
	protected $viewRootDirectoryName = DIRNAME_VIEWS;

	protected function constructView($path = false) {
		$path = '/' . trim($path, '/');
		$this->viewPath = $path;
	}

	public function getThemeDirectory() {return $this->themeAbsolutePath;}
	public function getViewPath() {return $this->viewPath;}
	/**
	 * gets the relative theme path for use in templates
	 * @access public
	 * @return string $themePath
	*/
	public function getThemePath() { return $this->themeRelativePath; }
	public function getThemeHandle() {return $this->themeHandle;}
	
	public function setInnerContentFile($innerContentFile) {
		$this->innerContentFile = $innerContentFile;
	}

	public function setViewRootDirectoryName($directory) {
		$this->viewRootDirectoryName = $directory;
	}

	public function inc($file, $args = array()) {
		extract($args);
		extract($this->getScopeItems());
		$env = Environment::get();
		include($env->getPath(DIRNAME_THEMES . '/' . $this->themeHandle . '/' . $file, $this->themePkgHandle));
	}

	/**
	 * A shortcut to posting back to the current page with a task and optional parameters. Only works in the context of 
	 * @param string $action
	 * @param string $task
	 * @return string $url
	 */
	public function action($action) {
		$a = func_get_args();
		array_unshift($a, $this->viewPath);
		$ret = call_user_func_array(array($this, 'url'), $a);
		return $ret;
	}

	public function setViewTheme($theme) {
		if (is_object($theme)) {
			$this->themeHandle = $theme->getPageThemeHandle();
		} else {
			$this->themeHandle = $theme;
		}
	}

	/** 
	 * Load all the theme-related variables for which theme to use for this request.
	 */
	protected function loadViewThemeObject() {
		$env = Environment::get();	
		if ($this->themeHandle) {
			if ($this->themeHandle != VIEW_CORE_THEME && $this->themeHandle != 'dashboard') {
				$this->themeObject = PageTheme::getByHandle($this->themeHandle);
				$this->themePkgHandle = $this->themeObject->getPackageHandle();
			}
			$this->themeAbsolutePath = $env->getPath(DIRNAME_THEMES . '/' . $this->themeHandle, $this->themePkgHandle);
			$this->themeRelativePath = $env->getURL(DIRNAME_THEMES . '/' . $this->themeHandle, $this->themePkgHandle);
		}
	}

	/** 
	 * Begin the render
	 */
	public function start($state) {}

	public function setupRender() {
		// Set the theme object that we should use for this requested page.
		// Only run setup if the theme is unset. Usually it will be but if we set it
		// programmatically we already have a theme.
		$this->loadViewThemeObject();
		$env = Environment::get();
		$this->setInnerContentFile($env->getPath($this->viewRootDirectoryName . '/' . trim($this->viewPath, '/') . '.php', $this->themePkgHandle));
		if ($this->themeHandle) {
			if (file_exists(DIR_FILES_THEMES_CORE . '/' . DIRNAME_THEMES_CORE . '/' . $this->themeHandle . '.php')) {
				$this->setViewTemplate($env->getPath(DIRNAME_THEMES . '/' . DIRNAME_THEMES_CORE . '/' . $this->themeHandle . '.php'));
			} else {
				$this->setViewTemplate($env->getPath(DIRNAME_THEMES . '/' . $this->themeHandle . '/' . FILENAME_THEMES_VIEW, $this->themePkgHandle));
			}
		}
	}

	public function startRender() {
		// First the starting gun.
		Events::fire('on_start', $this);
		parent::startRender();
	}

	protected function onBeforeGetContents() {
		Events::fire('on_before_render', $this);
		if ($this->themeHandle == VIEW_CORE_THEME) {
			$_pt = new ConcretePageTheme();
			$_pt->registerAssets();
		} else if (is_object($this->themeObject)) {
			$this->themeObject->registerAssets();
		}
	}

	public function renderViewContents($scopeItems) {
		extract($scopeItems);
		if ($this->innerContentFile) {
			ob_start();
			include($this->innerContentFile);
			$innerContent = ob_get_contents();
			ob_end_clean();
		}

		if (file_exists($this->template)) {
			ob_start();
			$this->onBeforeGetContents();
			include($this->template);
			$contents = ob_get_contents();
			$this->onAfterGetContents();
			ob_end_clean();
			return $contents;
		} else {
			return $innerContent;
		}
	}

	public function finishRender($contents) {
		$ret = Events::fire('on_page_output', $contents);
		if($ret != '') {
			$contents = $ret;
		}
		Events::fire('on_render_complete', $this);
		return $contents;
	}

	/** 
	 * Function responsible for outputting header items
	 * @access private
	 */
	public function markHeaderAssetPosition() {
		print '<!--ccm:assets:' . Asset::ASSET_POSITION_HEADER . '//-->';
	}
	
	/** 
	 * Function responsible for outputting footer items
	 * @access private
	 */
	public function markFooterAssetPosition() {
		print '<!--ccm:assets:' . Asset::ASSET_POSITION_FOOTER . '//-->';
	}

	public function postProcessViewContents($contents) {
		$responseGroup = ResponseAssetGroup::get();
		$assets = $responseGroup->getAssetsToOutput();

		$contents = $this->replaceAssetPlaceholders($assets, $contents);

		// replace any empty placeholders
		$contents = $this->replaceEmptyAssetPlaceholders($contents);

		return $contents;
	}


	protected function sortAssetsByWeightDescending($assetA, $assetB) {
		$weightA = $assetA->getAssetWeight();
		$weightB = $assetB->getAssetWeight();

		if ($weightA == $weightB) {
			return 0;
		}

		return $weightA < $weightB ? 1 : -1;
	}

	protected function sortAssetsByPostProcessDescending($assetA, $assetB) {
		$ppA = ($assetA instanceof Asset && $assetA->assetSupportsPostProcessing());
		$ppB = ($assetB instanceof Asset && $assetB->assetSupportsPostProcessing());
		if ($ppA && $ppB) {
			return 0;
		}
		if ($ppA && !$ppB) {
			return -1;
		}

		if (!$ppA && $ppB) {
			return 1;
		}
		if (!$ppA && !$ppB) {
			return 0;
		}
	}

	protected function postProcessAssets($assets) {
		$c = Page::getCurrentPage();
		if (!is_object($c) || !ENABLE_ASSET_CACHE) {
			return $assets;
		}
		// goes through all assets in this list, creating new URLs and post-processing them where possible.
		$segment = 0;
		$subassets[$segment] = array();
		for ($i = 0; $i < count($assets); $i++) {
			$asset = $assets[$i];
			$nextasset = $assets[$i+1];
			$subassets[$segment][] = $asset;
			if ($asset instanceof Asset && $nextasset instanceof Asset) {
				if ($asset->getAssetType() != $nextasset->getAssetType()) {
					$segment++;
				} else if (!$asset->assetSupportsPostProcessing() || !$nextasset->assetSupportsPostProcessing()) {
					$segment++;
				}
			} else {
				$segment++;
			}
		}

		// now we have a sub assets array with different segments split by post process and non-post-process
		$return = array();
		foreach($subassets as $segment => $assets) {
			if ($assets[0] instanceof Asset && $assets[0]->assetSupportsPostProcessing()) {
				// this entire segment can be post processed together
				$class = Loader::helper('text')->camelcase($assets[0]->getAssetType()) . 'Asset';
				$assets = call_user_func(array($class, 'postprocess'), $assets);
			}
			$return = array_merge($return, $assets);
		}
		return $return;
	}

	protected function replaceEmptyAssetPlaceholders($pageContent) {
		foreach(array('<!--ccm:assets:' . Asset::ASSET_POSITION_HEADER . '//-->', '<!--ccm:assets:' . Asset::ASSET_POSITION_FOOTER . '//-->') as $comment) {
			$pageContent = str_replace($comment, '', $pageContent);
		}
		return $pageContent;
	}

	protected function replaceAssetPlaceholders($outputAssets, $pageContent) {
		$outputItems = array();
		foreach($outputAssets as $position => $assets) {
			$output = '';
			if (is_array($assets['weighted'])) {
				$weightedAssets = $assets['weighted'];
				usort($weightedAssets, array($this, 'sortAssetsByWeightDescending'));
				$transformed = $this->postProcessAssets($weightedAssets);
				foreach($transformed as $item) {
					$itemstring = (string) $item;
					if (!in_array($itemstring, $outputItems)) {
						$output .= $this->outputAssetIntoView($item);
						$outputItems[] = $itemstring;
					}
				}
			}
			if (is_array($assets['unweighted'])) {
				// now the unweighted
				$unweightedAssets = $assets['unweighted'];
				usort($unweightedAssets, array($this, 'sortAssetsByPostProcessDescending'));
				$transformed = $this->postProcessAssets($unweightedAssets);
				foreach($transformed as $item) {
					$itemstring = (string) $item;
					if (!in_array($itemstring, $outputItems)) {
						$output .= $this->outputAssetIntoView($item);
						$outputItems[] = $itemstring;
					}
				}
			}
			$pageContent = str_replace('<!--ccm:assets:' . $position . '//-->', $output, $pageContent);
		}
		return $pageContent;				
	}
	
	protected function outputAssetIntoView($item) {
		return $item . "\n";			
	}

}