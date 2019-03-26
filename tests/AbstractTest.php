<?php

declare(strict_types = 1);

namespace EcEuropa\Toolkit\Tests;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

/**
 * Abstract test class for Toolkit commands.
 */
abstract class AbstractTest extends TestCase
{
    /**
     * {@inheritdoc}
     */
    protected function setUp()
    {
        if (!is_dir($this->getSandboxRoot())) {
            mkdir($this->getSandboxRoot());
        }
        $filesystem = new Filesystem();
        $filesystem->chmod($this->getSandboxRoot(), 0777, umask(), true);
        $filesystem->remove(glob($this->getSandboxRoot() . '/*'));
    }

    /**
     * Helper function to assert contain / not contain expectations.
     *
     * @param string $content
     * @param array  $expected
     */
    protected function assertContainsNotContains($content, array $expected)
    {
        if (!empty($expected['contains'])) {
            $this->assertContains($this->trimEachLine($expected['contains']), $this->trimEachLine($content));
            $this->assertEquals(substr_count($this->trimEachLine($content), $this->trimEachLine($expected['contains'])), 1, 'String found more than once.');
        }
        if (!empty($expected['not_contains'])) {
            $this->assertNotContains($this->trimEachLine($expected['not_contains']), $this->trimEachLine($content));
        }
    }

    /**
     * Trim each line of a blob of text, useful when asserting on multiline strings.
     *
     * @param string $text
     *    Untrimmed text.
     *
     * @return string
     *    Trimmed text.
     */
    protected function trimEachLine($text) {
      return implode(PHP_EOL, array_map('trim', explode(PHP_EOL, $text)));
    }

    /**
     * @return \Composer\Autoload\ClassLoader
     */
    protected function getClassLoader()
    {
        return require __DIR__ . '/../vendor/autoload.php';
    }

    /**
     * @param $filepath
     *
     * @return mixed
     */
    protected function getFixtureContent($filepath)
    {
        return Yaml::parse(file_get_contents(__DIR__ . "/fixtures/{$filepath}"));
    }

    /**
     * @param $name
     *
     * @return string
     */
    protected function getSandboxFilepath($name)
    {
        return $this->getSandboxRoot() . '/' . $name;
    }

    /**
     * @param $name
     *
     * @return string
     */
    protected function getSandboxRoot()
    {
        return __DIR__ . '/sandbox';
    }
}