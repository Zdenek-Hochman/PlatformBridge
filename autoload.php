<?php

/**
 * Class Autoload
 *
 * This class handles autoloading of classes.
 * Compatible with Composer autoloader - will skip registration if Composer is present.
 */
final class Autoload
{
    private static $CORE_DIR;

    private $rootDirectory;
    private static $instance;
    private $index = [];

    protected function __construct()
    {
        self::$CORE_DIR = __DIR__ . "/src/";

        $this->rootDirectory = self::$CORE_DIR;

        $this->GenerateIndex();
    }

    /**
     * Generates an index of classes by scanning the 'classes/' directory.
     *
     * @return array The generated index of classes.
     */
    private function GenerateIndex(): array
    {
        $this->index = $this->GetClassesFromDirs([
            'AI/',
            'FieldFactory/',
            'TemplateEngine/',
            'Translator/',
            'Handler/',
            'Parser/',
            'Error/',
            'App/'
        ]);

        return $this->index;
    }

    /**
     * Retrieves all classes from a given directory path.
     *
     * @param string $path The directory path to search for classes.
     * @return array An array of classes found in the directory, along with their paths.
     */
    private function GetClassesFromDirs(array $paths): array
    {
        $classes = [];
        $rootDir = $this->NormalizeDirectory(self::$CORE_DIR);

        foreach ($paths as $path) {
            $fullPath = $rootDir . $path;
            if (!is_dir($fullPath)) {
                continue;
            }
            foreach (scandir($fullPath, SCANDIR_SORT_NONE) as $file) {
                if ($file[0] != '.' && $file !== 'autoload.php' && $file !== 'index.php') {
                    if (is_dir($rootDir . $path . $file)) {
                        $classes = array_merge($classes, $this->GetClassesFromDirs([$path . $file . '/']));
                    } elseif (substr($file, -4) == '.php') {
                        $content = file_get_contents($rootDir . $path . $file);
                        $namespacePattern = '/^namespace\s+([^;]+);/m';

                        if (preg_match($namespacePattern, $content, $M)) {
                            $namespace = $M[1];
                        } else {
                            $namespace = '';
                        }

                        $namePattern = '[a-z_\x7f-\xff][a-z0-9_\x7f-\xff]*';
                        $nameWithNsPattern = '(?:\\\\?(?:' . $namePattern . '\\\\)*' . $namePattern . ')';

                        $pattern = '~(?<!\w)((abstract\s+)?class|interface|trait|enum(:\s*\w+)?)\s+(?P<classname>' . basename($file, '.php') . '?)'
                            . '(?:\s+extends\s+' . $nameWithNsPattern . ')?(?:\s+implements\s+' . $nameWithNsPattern . '(?:\s*,\s*' . $nameWithNsPattern . ')*)?(?:\s*:\s+\w+)?(\s*\{|::class)~i';

                        if (preg_match($pattern, $content, $M)) {
                            $classes[$namespace . '\\' . $M['classname']] = ['path' => $path . $file];
                        }
                    }
                }
            }
        }

        return $classes;
    }

    /**
     * Normalizes the given directory path by removing trailing slashes and backslashes,
     * and appending the appropriate directory separator.
     *
     * @param string $directory The directory path to normalize.
     * @return string The normalized directory path.
     */
    private function NormalizeDirectory(string $directory): string
    {
        return rtrim($directory, '/\\') . DIRECTORY_SEPARATOR;
    }

    /**
     * Get an instance of the class.
     *
     * @return mixed The instance of the class.
     */
    public static function GetInstance(): mixed
    {
        static::$instance = new static();
        return static::$instance;
    }

    public function Load(string $className): void
    {
        $fileName  = '';

        if ($lastNsPos = strrpos($className, '\\')) {
            $fileName  = str_replace('\\', DIRECTORY_SEPARATOR, substr($className, 0, $lastNsPos)) . DIRECTORY_SEPARATOR;
            $className = substr($className, $lastNsPos + 1);
        }

        $fileName .= str_replace('_', DIRECTORY_SEPARATOR, $className);
        $indexKey = str_replace('/', '\\', $fileName);

        $extensions = ['.class.php', '.interface.php', '.enum.php', '.abstract.php', '.model.php', '.controller.php'];

        foreach ($extensions as $extension) {
            $filePath = $this->rootDirectory . $fileName . $extension;
            if (file_exists($filePath)) {
                require_once $filePath;
                return;
            }
        }

        // Pokud soubor nebyl nalezen, použijte původní logiku
        if ((isset($this->index[$indexKey]) && $this->index[$indexKey]['path'])) {
            require_once  $this->rootDirectory . $this->index[$indexKey]['path'];
        }
    }
}

// Register autoloader only if Composer autoloader is not present or class not yet registered
if (!class_exists('Composer\\Autoload\\ClassLoader', false)) {
    spl_autoload_register([Autoload::GetInstance(), 'Load']);
}
