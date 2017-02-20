<?php

namespace Spatie\Browsershot;

use Exception;
use Intervention\Image\ImageManager;

/**
 * Class Browsershot.
 */
class Browsershot
{
    /**
     * @var int
     */
    private $width;

    /**
     * @var int
     */
    protected $height;

    /**
     * @var int
     */
    protected $quality;

    /**
     * @var string
     */
    protected $backgroundColor;

    /**
     * @var int
     */
    protected $url;

    /**
     * @var string
     */
    protected $binPath;

    /**
     * @var int
     */
    protected $timeout;

    public function __construct($binPath = '', $width = 640, $height = 480, $quality = 60, $timeout = 5000, $backgroundColor = null)
    {
        if ($binPath == '') {
            $binPath = realpath(dirname(__FILE__).'/../../../bin/phantomjs');
        }

        $this->binPath = $binPath;
        $this->width = $width;
        $this->height = $height;
        $this->quality = $quality;
        $this->backgroundColor = $backgroundColor;
        $this->timeout = $timeout;

        return $this;
    }

    /**
     * @param string $binPath
     *
     * @return $this
     */
    public function setBinPath($binPath)
    {
        $this->binPath = $binPath;

        return $this;
    }

    /**
     * @param int $width
     *
     * @return $this
     *
     * @throws \Exception
     */
    public function setWidth($width)
    {
        if (!is_numeric($width)) {
            throw new Exception('Width must be numeric');
        }

        $this->width = $width;

        return $this;
    }

    /**
     * @param int $height
     *
     * @return $this
     *
     * @throws \Exception
     */
    public function setHeight($height)
    {
        if (!is_numeric($height)) {
            throw new Exception('Height must be numeric');
        }

        $this->height = $height;

        return $this;
    }

    /**
     * Set the image quality.
     *
     * @param $quality
     *
     * @return $this
     *
     * @throws \Exception
     */
    public function setQuality($quality)
    {
        if (!is_numeric($quality) || $quality < 1 || $quality > 100) {
            throw new Exception('Quality must be a numeric value between 1 - 100');
        }

        $this->quality = $quality;

        return $this;
    }

    /**
     * Set the background color.
     *
     * @param string $backgroundColor
     *
     * @return $this
     *
     * @throws \Exception
     */
    public function setBackgroundColor($backgroundColor)
    {
        if (!strlen($backgroundColor) > 0) {
            throw new Exception('No background color specified');
        }

        $this->backgroundColor = $backgroundColor;

        return $this;
    }

    /**
     * Set to height so the whole page will be rendered.
     *
     * @return $this
     */
    public function setHeightToRenderWholePage()
    {
        $this->height = 0;

        return $this;
    }

    /**
     * @param string $url
     *
     * @return $this
     *
     * @throws \Exception
     */
    public function setUrl($url)
    {
        if (!strlen($url) > 0) {
            throw new Exception('No url specified');
        }

        $this->url = $url;

        return $this;
    }

    /**
     * @param int $timeout
     *
     * @return $this
     *
     * @throws \Exception
     */
    public function setTimeout($timeout)
    {
        if (!is_numeric($timeout)) {
            throw new Exception('Height must be numeric');
        }

        $this->timeout = $timeout;

        return $this;
    }

    /**
     * Convert the webpage to an image.
     *
     * @param string $targetFile The path of the file where the screenshot should be saved
     *
     * @return bool
     *
     * @throws \Exception
     */
    public function save($targetFile)
    {
        if ($targetFile == '') {
            throw new Exception('targetfile not set');
        }

        if (!in_array(strtolower(pathinfo($targetFile, PATHINFO_EXTENSION)), ['jpeg', 'jpg', 'png'])) {
            throw new Exception('targetfile extension not valid');
        }

        if ($this->url == '') {
            throw new Exception('url not set');
        }

        if (filter_var($this->url, FILTER_VALIDATE_URL) === false) {
            throw new Exception('url is invalid');
        }

        if (!file_exists($this->binPath)) {
            throw new Exception('binary does not exist');
        }

        $this->takeScreenShot($targetFile);

        if (!file_exists($targetFile) or filesize($targetFile) < 1024) {
            throw new Exception('could not create screenshot');
        }

        if ($this->height > 0) {
            $imageManager = new ImageManager();
            $imageManager
                ->make($targetFile)
                ->crop($this->width, $this->height, 0, 0)
                ->save($targetFile, $this->quality);
        }

        return true;
    }

    /**
     * Take the screenshot.
     *
     * @param $targetFile
     */
    protected function takeScreenShot($targetFile)
    {
        $tempJsFileHandle = tmpfile();

        fwrite($tempJsFileHandle, $this->getPhantomJsScript($targetFile));
        $tempFileName = stream_get_meta_data($tempJsFileHandle)['uri'];
        $cmd = escapeshellcmd("{$this->binPath} --ssl-protocol=any --ignore-ssl-errors=true ".$tempFileName);

        shell_exec($cmd);

        fclose($tempJsFileHandle);
    }

    /**
     * Get the script to be executed by phantomjs.
     *
     * @param string $targetFile
     *
     * @return string
     */
    protected function getPhantomJsScript($targetFile)
    {
        return "
            var page = require('webpage').create();
            page.settings = {
                javascriptEnabled: true,
                resourceTimeout: {$this->timeout},
                loadImages: true,
                localToRemoteUrlAccessEnabled: true,
            };            
            page.viewportSize = { width: ".$this->width.($this->height == 0 ? '' : ', height: '.$this->height)." };
            page.open('{$this->url}', function() {
                    page.evaluate(function() {
                        var styles = '', 
                            removeClass = document.querySelectorAll('.section-vh'),
                            removeAnimation = document.querySelectorAll('.anim-content, .animated, .animate');
                            
                        for (var i = 0; i < removeClass.length; i++) {
                            removeClass[i].classList.remove('section-vh');
                        }
                        
                        for (var i = 0; i < removeAnimation.length; i++) {
                            removeAnimation[i].classList.remove('anim-content');
                            removeAnimation[i].classList.remove('animated');
                            removeAnimation[i].classList.remove('animate');
                        }
                        
                        if (".($this->backgroundColor ? 'true' : 'false').") {
                            styles += 'body { background: {$this->backgroundColor} }';
                        }

                        var style = document.createElement('style'),
                            text = document.createTextNode(styles);
                        style.setAttribute('type', 'text/css');
                        style.appendChild(text);
                        document.head.insertBefore(style, document.head.firstChild);
                    });
                
                var renderOut = function() {
                    page.render('{$targetFile}');
                    phantom.exit();
                };
                setTimeout(renderOut, {$this->timeout});
            });
        ";
    }
}
