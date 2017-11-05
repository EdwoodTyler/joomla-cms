<?php
/**
 * @package    Joomla.Platform
 *
 * @copyright  Copyright (C) 2005 - 2017 Open Source Matters, Inc. All rights reserved.
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('JPATH_PLATFORM') or die;

/**
 * Static class to handle loading of libraries.
 *
 * @package  Joomla.Platform
 * @since    11.1
 */
abstract class JLoader
{
	/**
	 * Container for already imported library paths.
	 *
	 * @var    array
	 * @since  11.1
	 */
	protected static $classes = array();

	/**
	 * Container for already imported library paths.
	 *
	 * @var    array
	 * @since  11.1
	 */
	protected static $imported = array();

	/**
	 * Container for registered library class prefixes and path lookups.
	 *
	 * @var    array
	 * @since  12.1
	 */
	protected static $prefixes = array();

	/**
	 * Holds proxy classes and the class names the proxy.
	 *
	 * @var    array
	 * @since  3.2
	 */
	protected static $classAliases = array();

	/**
	 * Holds the inverse lookup for proxy classes and the class names the proxy.
	 *
	 * @var    array
	 * @since  3.4
	 */
	protected static $classAliasesInverse = array();

	/**
	 * Container for namespace => path map.
	 *
	 * @var    array
	 * @since  12.3
	 */
	protected static $namespaces = array('psr0' => array(), 'psr4' => array());

	/**
	 * Holds a reference for all deprecated aliases (mainly for use by a logging platform).
	 *
	 * @var    array
	 * @since  3.6.3
	 */
	protected static $deprecatedAliases = array();

	/**
	 * The root folders where extensions can be found.
	 *
	 * @var    array
	 * @since  __DEPLOY_VERSION__
	 */
	protected static $extensionRootFolders = array();

	/**
	 * Method to discover classes of a given type in a given path.
	 *
	 * @param   string   $classPrefix  The class name prefix to use for discovery.
	 * @param   string   $parentPath   Full path to the parent folder for the classes to discover.
	 * @param   boolean  $force        True to overwrite the autoload path value for the class if it already exists.
	 * @param   boolean  $recurse      Recurse through all child directories as well as the parent path.
	 *
	 * @return  void
	 *
	 * @since   11.1
	 */
	public static function discover($classPrefix, $parentPath, $force = true, $recurse = false)
	{
		try
		{
			if ($recurse)
			{
				$iterator = new RecursiveIteratorIterator(
					new RecursiveDirectoryIterator($parentPath),
					RecursiveIteratorIterator::SELF_FIRST
				);
			}
			else
			{
				$iterator = new DirectoryIterator($parentPath);
			}

			/* @type  $file  DirectoryIterator */
			foreach ($iterator as $file)
			{
				$fileName = $file->getFilename();

				// Only load for php files.
				if ($file->isFile() && $file->getExtension() === 'php')
				{
					// Get the class name and full path for each file.
					$class = strtolower($classPrefix . preg_replace('#\.php$#', '', $fileName));

					// Register the class with the autoloader if not already registered or the force flag is set.
					if ($force || empty(self::$classes[$class]))
					{
						self::register($class, $file->getPath() . '/' . $fileName);
					}
				}
			}
		}
		catch (UnexpectedValueException $e)
		{
			// Exception will be thrown if the path is not a directory. Ignore it.
		}
	}

	/**
	 * Method to get the list of registered classes and their respective file paths for the autoloader.
	 *
	 * @return  array  The array of class => path values for the autoloader.
	 *
	 * @since   11.1
	 */
	public static function getClassList()
	{
		return self::$classes;
	}

	/**
	 * Method to get the list of deprecated class aliases.
	 *
	 * @return  array  An associative array with deprecated class alias data.
	 *
	 * @since   3.6.3
	 */
	public static function getDeprecatedAliases()
	{
		return self::$deprecatedAliases;
	}

	/**
	 * Method to get the list of registered namespaces.
	 *
	 * @param   string  $type  Defines the type of namespace, can be prs0 or psr4.
	 *
	 * @return  array  The array of namespace => path values for the autoloader.
	 *
	 * @since   12.3
	 */
	public static function getNamespaces($type = 'psr0')
	{
		if ($type !== 'psr0' && $type !== 'psr4')
		{
			throw new InvalidArgumentException('Type needs to be prs0 or psr4!');
		}

		return self::$namespaces[$type];
	}

	/**
	 * Loads a class from specified directories.
	 *
	 * @param   string  $key   The class name to look for (dot notation).
	 * @param   string  $base  Search this directory for the class.
	 *
	 * @return  boolean  True on success.
	 *
	 * @since   11.1
	 */
	public static function import($key, $base = null)
	{
		// Only import the library if not already attempted.
		if (!isset(self::$imported[$key]))
		{
			// Setup some variables.
			$success = false;
			$parts   = explode('.', $key);
			$class   = array_pop($parts);
			$base    = (!empty($base)) ? $base : __DIR__;
			$path    = str_replace('.', DIRECTORY_SEPARATOR, $key);

			// Handle special case for helper classes.
			if ($class === 'helper')
			{
				$class = ucfirst(array_pop($parts)) . ucfirst($class);
			}
			// Standard class.
			else
			{
				$class = ucfirst($class);
			}

			// If we are importing a library from the Joomla namespace set the class to autoload.
			if (strpos($path, 'joomla') === 0)
			{
				// Since we are in the Joomla namespace prepend the classname with J.
				$class = 'J' . $class;

				// Only register the class for autoloading if the file exists.
				if (is_file($base . '/' . $path . '.php'))
				{
					self::$classes[strtolower($class)] = $base . '/' . $path . '.php';
					$success = true;
				}
			}
			/*
			 * If we are not importing a library from the Joomla namespace directly include the
			 * file since we cannot assert the file/folder naming conventions.
			 */
			else
			{
				// If the file exists attempt to include it.
				if (is_file($base . '/' . $path . '.php'))
				{
					$success = (bool) include_once $base . '/' . $path . '.php';
				}
			}

			// Add the import key to the memory cache container.
			self::$imported[$key] = $success;
		}

		return self::$imported[$key];
	}

	/**
	 * Load the file for a class.
	 *
	 * @param   string  $class  The class to be loaded.
	 *
	 * @return  boolean  True on success
	 *
	 * @since   11.1
	 */
	public static function load($class)
	{
		// Sanitize class name.
		$key = strtolower($class);

		// If the class already exists do nothing.
		if (class_exists($class, false))
		{
			return true;
		}

		// If the class is registered include the file.
		if (isset(self::$classes[$key]))
		{
			$found = (bool) include_once self::$classes[$key];

			if ($found)
			{
				self::loadAliasFor($class);
			}

			// If the class doesn't exists, we probably have a class alias available
			if (!class_exists($class, false))
			{
				// Search the alias class, first none namespaced and then namespaced
				$original = array_search($class, self::$classAliases) ? : array_search('\\' . $class, self::$classAliases);

				// When we have an original and the class exists an alias should be created
				if ($original && class_exists($original, false))
				{
					class_alias($original, $class);
				}
			}

			return true;
		}

		return false;
	}

	/**
	 * Directly register a class to the autoload list.
	 *
	 * @param   string   $class  The class name to register.
	 * @param   string   $path   Full path to the file that holds the class to register.
	 * @param   boolean  $force  True to overwrite the autoload path value for the class if it already exists.
	 *
	 * @return  void
	 *
	 * @since   11.1
	 */
	public static function register($class, $path, $force = true)
	{
		// When an alias exists, register it as well
		if (array_key_exists($class, self::$classAliases))
		{
			self::register(self::stripFirstBackslash(self::$classAliases[$class]), $path, $force);
		}

		// Sanitize class name.
		$class = strtolower($class);

		// Only attempt to register the class if the name and file exist.
		if (!empty($class) && is_file($path))
		{
			// Register the class with the autoloader if not already registered or the force flag is set.
			if ($force || empty(self::$classes[$class]))
			{
				self::$classes[$class] = $path;
			}
		}
	}

	/**
	 * Register a class prefix with lookup path.  This will allow developers to register library
	 * packages with different class prefixes to the system autoloader.  More than one lookup path
	 * may be registered for the same class prefix, but if this method is called with the reset flag
	 * set to true then any registered lookups for the given prefix will be overwritten with the current
	 * lookup path. When loaded, prefix paths are searched in a "last in, first out" order.
	 *
	 * @param   string   $prefix   The class prefix to register.
	 * @param   string   $path     Absolute file path to the library root where classes with the given prefix can be found.
	 * @param   boolean  $reset    True to reset the prefix with only the given lookup path.
	 * @param   boolean  $prepend  If true, push the path to the beginning of the prefix lookup paths array.
	 *
	 * @return  void
	 *
	 * @throws  RuntimeException
	 *
	 * @since   12.1
	 */
	public static function registerPrefix($prefix, $path, $reset = false, $prepend = false)
	{
		// Verify the library path exists.
		if (!file_exists($path))
		{
			$path = (str_replace(JPATH_ROOT, '', $path) == $path) ? basename($path) : str_replace(JPATH_ROOT, '', $path);

			throw new RuntimeException('Library path ' . $path . ' cannot be found.', 500);
		}

		// If the prefix is not yet registered or we have an explicit reset flag then set set the path.
		if ($reset || !isset(self::$prefixes[$prefix]))
		{
			self::$prefixes[$prefix] = array($path);
		}
		// Otherwise we want to simply add the path to the prefix.
		else
		{
			if ($prepend)
			{
				array_unshift(self::$prefixes[$prefix], $path);
			}
			else
			{
				self::$prefixes[$prefix][] = $path;
			}
		}
	}

	/**
	 * Offers the ability for "just in time" usage of `class_alias()`.
	 * You cannot overwrite an existing alias.
	 *
	 * @param   string          $alias     The alias name to register.
	 * @param   string          $original  The original class to alias.
	 * @param   string|boolean  $version   The version in which the alias will no longer be present.
	 *
	 * @return  boolean  True if registration was successful. False if the alias already exists.
	 *
	 * @since   3.2
	 */
	public static function registerAlias($alias, $original, $version = false)
	{
		if (!isset(self::$classAliases[$alias]))
		{
			self::$classAliases[$alias] = $original;

			$original = self::stripFirstBackslash($original);

			if (!isset(self::$classAliasesInverse[$original]))
			{
				self::$classAliasesInverse[$original] = array($alias);
			}
			else
			{
				self::$classAliasesInverse[$original][] = $alias;
			}

			// If given a version, log this alias as deprecated
			if ($version)
			{
				self::$deprecatedAliases[] = array('old' => $alias, 'new' => $original, 'version' => $version);
			}

			return true;
		}

		return false;
	}

	/**
	 * Register a namespace to the autoloader. When loaded, namespace paths are searched in a "last in, first out" order.
	 *
	 * @param   string   $namespace  A case sensitive Namespace to register.
	 * @param   string   $path       A case sensitive absolute file path to the library root where classes of the given namespace can be found.
	 * @param   boolean  $reset      True to reset the namespace with only the given lookup path.
	 * @param   boolean  $prepend    If true, push the path to the beginning of the namespace lookup paths array.
	 * @param   string   $type       Defines the type of namespace, can be prs0 or psr4.
	 *
	 * @return  void
	 *
	 * @throws  RuntimeException
	 *
	 * @note    The default argument of $type will be changed in J4 to be 'psr4'
	 * @since   12.3
	 */
	public static function registerNamespace($namespace, $path, $reset = false, $prepend = false, $type = 'psr0')
	{
		if ($type !== 'psr0' && $type !== 'psr4')
		{
			throw new InvalidArgumentException('Type needs to be prs0 or psr4!');
		}

		// Verify the library path exists.
		if (!file_exists($path))
		{
			$path = (str_replace(JPATH_ROOT, '', $path) == $path) ? basename($path) : str_replace(JPATH_ROOT, '', $path);

			throw new RuntimeException('Library path ' . $path . ' cannot be found.', 500);
		}

		// If the namespace is not yet registered or we have an explicit reset flag then set the path.
		if ($reset || !isset(self::$namespaces[$type][$namespace]))
		{
			self::$namespaces[$type][$namespace] = array($path);
		}

		// Otherwise we want to simply add the path to the namespace.
		else
		{
			if ($prepend)
			{
				array_unshift(self::$namespaces[$type][$namespace], $path);
			}
			else
			{
				self::$namespaces[$type][$namespace][] = $path;
			}
		}
	}

	/**
	 * Root folders where extensions can be found. For example:
	 * JLoader::registerExtensionRootFolder(JPATH_SITE, 'Site');
	 * JLoader::registerExtensionRootFolder(JPATH_ADMINISTRATOR, 'Administrator');
	 *
	 * @param   string  $key   The key.
	 * @param   string  $path  A absolute file path to the root where extensions can be found.
	 *
	 * @return  void
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public static function registerExtensionRootFolder($key, $path)
	{
		self::$extensionRootFolders[$key] = $path;
	}

	/**
	 * Method to setup the autoloaders for the Joomla Platform.
	 * Since the SPL autoloaders are called in a queue we will add our explicit
	 * class-registration based loader first, then fall back on the autoloader based on conventions.
	 * This will allow people to register a class in a specific location and override platform libraries
	 * as was previously possible.
	 *
	 * @param   boolean  $enablePsr       True to enable autoloading based on PSR-0.
	 * @param   boolean  $enablePrefixes  True to enable prefix based class loading (needed to auto load the Joomla core).
	 * @param   boolean  $enableClasses   True to enable class map based class loading (needed to auto load the Joomla core).
	 *
	 * @return  void
	 *
	 * @since   12.3
	 */
	public static function setup($enablePsr = true, $enablePrefixes = true, $enableClasses = true)
	{
		if ($enableClasses)
		{
			// Register the class map based autoloader.
			spl_autoload_register(array('JLoader', 'load'));
		}

		if ($enablePrefixes)
		{
			// Register the J prefix and base path for Joomla platform libraries.
			self::registerPrefix('J', JPATH_PLATFORM . '/joomla');

			// Register the prefix autoloader.
			spl_autoload_register(array('JLoader', '_autoload'));
		}

		if ($enablePsr)
		{
			// Register the PSR based autoloader.
			spl_autoload_register(array('JLoader', 'loadByPsr0'));
			spl_autoload_register(array('JLoader', 'loadByPsr4'));
			spl_autoload_register(array('JLoader', 'loadByExtension'));
			spl_autoload_register(array('JLoader', 'loadByAlias'));
		}
	}

	/**
	 * Method to autoload classes that are namespaced and do belong to an extension. The
	 * extension must have the following pattern to be autoloaded:
	 * - Component: Joomla\Component\Content\Site
	 * - Module:    Joomla\Module\ArticlesLatest\Administrator
	 * - Plugin:    Joomla\Plugin\System\Cache
	 *
	 * @param   string  $class  The fully qualified class name to autoload.
	 *
	 * @return  boolean  True on success, false otherwise.
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	public static function loadByExtension($class)
	{
		// Check if it is a namespaced class
		if (strrpos($class, '\\') === false)
		{
			return false;
		}

		// Splice into segments
		$segments = explode('\\', $class);

		// Check if there are enough segments
		if (count($segments) < 5)
		{
			return false;
		}

		// Check if it is an extension class
		if (!in_array($segments[1], array('Component', 'Module', 'Plugin')))
		{
			return false;
		}

		// Normally Administrator or Site
		$key = $segments[3];

		// If it is a plugin, then the key is empty
		if ($segments[1] == 'Plugin')
		{
			$key = '';
		}

		// Check if it is an extension class
		if (!array_key_exists($key, self::$extensionRootFolders))
		{
			return false;
		}

		// Define the root of the path
		$path = self::$extensionRootFolders[$key];

		// Add the extension specific folder to the path
		switch ($segments[1])
		{
			case 'Component':
				$name = strtolower($segments[2]);
				$path .= '/components/com_' . $name;
				break;
			case 'Module':
				// Convert the name of the extension from camelcase to underscore for module
				$name = strtolower(implode('_', self::fromCamelCase($segments[2], true)));
				$path .= '/modules/mod_' . $name;
				break;
			case 'Plugin':
				$group = strtolower($segments[2]);
				$name  = strtolower($segments[3]);
				$path .= '/plugins/' . $group . '/' . $name;
				break;
		}

		// Check if the extension supports a nice and clean folder structure
		if (file_exists($path . '/src'))
		{
			$path .= '/src';
		}

		// Extension can't be autoloaded
		if (!file_exists($path))
		{
			return false;
		}

		// Compile the namespace
		$ns = implode('\\', array_slice($segments, 0, 4));

		// Register the namespace
		self::registerNamespace($ns, $path, false, false, 'psr4');

		// Load the class by default PSR-4 routine
		return self::loadByPsr4($class);
	}

	/**
	 * Method to autoload classes that are namespaced to the PSR-4 standard.
	 *
	 * @param   string  $class  The fully qualified class name to autoload.
	 *
	 * @return  boolean  True on success, false otherwise.
	 *
	 * @since   3.7.0
	 */
	public static function loadByPsr4($class)
	{
		$class = self::stripFirstBackslash($class);

		// Find the location of the last NS separator.
		$pos = strrpos($class, '\\');

		// If one is found, we're dealing with a NS'd class.
		if ($pos !== false)
		{
			$classPath = str_replace('\\', DIRECTORY_SEPARATOR, substr($class, 0, $pos)) . DIRECTORY_SEPARATOR;
			$className = substr($class, $pos + 1);
		}
		// If not, no need to parse path.
		else
		{
			$classPath = null;
			$className = $class;
		}

		$classPath .= $className . '.php';

		// Loop through registered namespaces until we find a match.
		foreach (self::$namespaces['psr4'] as $ns => $paths)
		{
			$nsPath = trim(str_replace('\\', DIRECTORY_SEPARATOR, $ns), DIRECTORY_SEPARATOR);

			if (strpos($class, $ns) === 0)
			{
				// Loop through paths registered to this namespace until we find a match.
				foreach ($paths as $path)
				{
					$classFilePath = $path . DIRECTORY_SEPARATOR . substr_replace($classPath, '', 0, strlen($nsPath) + 1);

					// We check for class_exists to handle case-sensitive file systems
					if (file_exists($classFilePath) && !class_exists($class, false))
					{
						$found = (bool) include_once $classFilePath;

						if ($found)
						{
							self::loadAliasFor($class);
						}

						return $found;
					}
				}
			}
		}

		return false;
	}

	/**
	 * Method to autoload classes that are namespaced to the PSR-0 standard.
	 *
	 * @param   string  $class  The fully qualified class name to autoload.
	 *
	 * @return  boolean  True on success, false otherwise.
	 *
	 * @since   13.1
	 *
	 * @deprecated 4.0 this method will be removed
	 */
	public static function loadByPsr0($class)
	{
		$class = self::stripFirstBackslash($class);

		// Find the location of the last NS separator.
		$pos = strrpos($class, '\\');

		// If one is found, we're dealing with a NS'd class.
		if ($pos !== false)
		{
			$classPath = str_replace('\\', DIRECTORY_SEPARATOR, substr($class, 0, $pos)) . DIRECTORY_SEPARATOR;
			$className = substr($class, $pos + 1);
		}
		// If not, no need to parse path.
		else
		{
			$classPath = null;
			$className = $class;
		}

		$classPath .= str_replace('_', DIRECTORY_SEPARATOR, $className) . '.php';

		// Loop through registered namespaces until we find a match.
		foreach (self::$namespaces['psr0'] as $ns => $paths)
		{
			if (strpos($class, $ns) === 0)
			{
				// Loop through paths registered to this namespace until we find a match.
				foreach ($paths as $path)
				{
					$classFilePath = $path . DIRECTORY_SEPARATOR . $classPath;

					// We check for class_exists to handle case-sensitive file systems
					if (file_exists($classFilePath) && !class_exists($class, false))
					{
						$found = (bool) include_once $classFilePath;

						if ($found)
						{
							self::loadAliasFor($class);
						}

						return $found;
					}
				}
			}
		}

		return false;
	}

	/**
	 * Method to autoload classes that have been aliased using the registerAlias method.
	 *
	 * @param   string  $class  The fully qualified class name to autoload.
	 *
	 * @return  boolean  True on success, false otherwise.
	 *
	 * @since   3.2
	 */
	public static function loadByAlias($class)
	{
		$class = self::stripFirstBackslash($class);

		if (isset(self::$classAliases[$class]))
		{
			// Force auto-load of the regular class
			class_exists(self::$classAliases[$class], true);

			// Normally this shouldn't execute as the autoloader will execute applyAliasFor when the regular class is
			// auto-loaded above.
			if (!class_exists($class, false) && !interface_exists($class, false))
			{
				class_alias(self::$classAliases[$class], $class);
			}
		}
	}

	/**
	 * Applies a class alias for an already loaded class, if a class alias was created for it.
	 *
	 * @param   string  $class  We'll look for and register aliases for this (real) class name
	 *
	 * @return  void
	 *
	 * @since   3.4
	 */
	public static function applyAliasFor($class)
	{
		$class = self::stripFirstBackslash($class);

		if (isset(self::$classAliasesInverse[$class]))
		{
			foreach (self::$classAliasesInverse[$class] as $alias)
			{
				class_alias($class, $alias);
			}
		}
	}

	/**
	 * Autoload a class based on name.
	 *
	 * @param   string  $class  The class to be loaded.
	 *
	 * @return  boolean  True if the class was loaded, false otherwise.
	 *
	 * @since   11.3
	 */
	public static function _autoload($class)
	{
		foreach (self::$prefixes as $prefix => $lookup)
		{
			$chr = strlen($prefix) < strlen($class) ? $class[strlen($prefix)] : 0;

			if (strpos($class, $prefix) === 0 && ($chr === strtoupper($chr)))
			{
				return self::_load(substr($class, strlen($prefix)), $lookup);
			}
		}

		return false;
	}

	/**
	 * Load a class based on name and lookup array.
	 *
	 * @param   string  $class   The class to be loaded (without prefix).
	 * @param   array   $lookup  The array of base paths to use for finding the class file.
	 *
	 * @return  boolean  True if the class was loaded, false otherwise.
	 *
	 * @since   12.1
	 */
	private static function _load($class, $lookup)
	{
		// Split the class name into parts separated by camelCase.
		$parts = preg_split('/(?<=[a-z0-9])(?=[A-Z])/x', $class);
		$partsCount = count($parts);

		foreach ($lookup as $base)
		{
			// Generate the path based on the class name parts.
			$path = $base . '/' . implode('/', array_map('strtolower', $parts)) . '.php';

			// Load the file if it exists.
			if (file_exists($path))
			{
				$found = (bool) include_once $path;

				if ($found)
				{
					self::loadAliasFor($class);
				}

				return $found;
			}

			// Backwards compatibility patch

			// If there is only one part we want to duplicate that part for generating the path.
			if ($partsCount === 1)
			{
				// Generate the path based on the class name parts.
				$path = $base . '/' . implode('/', array_map('strtolower', array($parts[0], $parts[0]))) . '.php';

				// Load the file if it exists.
				if (file_exists($path))
				{
					$found = (bool) include_once $path;

					if ($found)
					{
						self::loadAliasFor($class);
					}

					return $found;
				}
			}
		}

		return false;
	}

	/**
	 * Loads the aliases for the given class.
	 *
	 * @param   string  $class  The class.
	 *
	 * @return  void
	 *
	 * @since   3.8.0
	 */
	private static function loadAliasFor($class)
	{
		if (!array_key_exists($class, self::$classAliasesInverse))
		{
			return;
		}

		foreach (self::$classAliasesInverse[$class] as $alias)
		{
			// Force auto-load of the alias class
			class_exists($alias, true);
		}
	}

	/**
	 * Strips the first backslash from the given class if present.
	 *
	 * @param   string  $class  The class to strip the first prefix from.
	 *
	 * @return  string  The striped class name.
	 *
	 * @since   3.8.0
	 */
	private static function stripFirstBackslash($class)
	{
		return $class && $class[0] === '\\' ? substr($class, 1) : $class;
	}

	/**
	 * Copied form Normalise class, JLoader should not have an external dependency.
	 *
	 * @param   string   $input    The string input (ASCII only).
	 * @param   boolean  $grouped  Optionally allows splitting on groups of uppercase characters.
	 *
	 * @return  string  The space separated string.
	 *
	 * @since   __DEPLOY_VERSION__
	 */
	private static function fromCamelCase($input, $grouped = false)
	{
		return $grouped
			? preg_split('/(?<=[^A-Z_])(?=[A-Z])|(?<=[A-Z])(?=[A-Z][^A-Z_])/x', $input)
			: trim(preg_replace('#([A-Z])#', ' $1', $input));
	}
}

// Check if jexit is defined first (our unit tests mock this)
if (!function_exists('jexit'))
{
	/**
	 * Global application exit.
	 *
	 * This function provides a single exit point for the platform.
	 *
	 * @param   mixed  $message  Exit code or string. Defaults to zero.
	 *
	 * @return  void
	 *
	 * @codeCoverageIgnore
	 * @since   11.1
	 */
	function jexit($message = 0)
	{
		exit($message);
	}
}

/**
 * Intelligent file importer.
 *
 * @param   string  $path  A dot syntax path.
 * @param   string  $base  Search this directory for the class.
 *
 * @return  boolean  True on success.
 *
 * @since   11.1
 */
function jimport($path, $base = null)
{
	return JLoader::import($path, $base);
}
