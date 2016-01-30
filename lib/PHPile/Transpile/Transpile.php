<?php

namespace PHPile\Transpile;

use PHPile\IO\IOInterface;
use PhpParser\NodeTraverser;
use PhpParser\ParserFactory;
use PhpParser\PrettyPrinter\Standard;

class Transpile
{
    use \PHPile\IO\IO;

    public function __construct(IOInterface $io)
    {
        $this->setIO($io);
    }

    public function transpile($srcPath, $dstPath)
    {
        $inplace = false;
        if ($srcPath == $dstPath) {
            // Inline replacement
            $inplace = true;
            $dstPath = $dstPath.uniqid();
        }

        // transpile based on target version
        $code = file_get_contents($srcPath);

//        $parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);
//        $stmts = $parser->parse('<?php @$a;');
//        print_r($stmts);
//        exit(1);

        // Parse into statements
        $parser = (new ParserFactory())->create(ParserFactory::PREFER_PHP7);
        $stmts = $parser->parse($code);

        // Custom traversal does the actual transpiling
        $traverser = $this->getTraverser();
        $stmts = $traverser->traverse($stmts);

//        echo "Old output:\n============================================\n";
//        $ah = new AnsiHighlight();
//        echo $ah->highlight($code);
//        echo "\n============================================\n";
//        echo "New output:\n============================================\n";
//        $ah = new AnsiHighlight();
//        echo $ah->highlight("<?php\n\n".$prettyPrinter->prettyPrint($stmts));

        $prettyPrinter = new Standard();

        if ($dstPath == '-') {
            // Output directly to stdout
            $this->getIo()->output("<?php \n". $prettyPrinter->prettyPrint($stmts));
        } else {
            file_put_contents($dstPath, "<?php \n".$prettyPrinter->prettyPrint($stmts));
        }

        // If inplace, we have to (atomically) rename (temp) dest to source
        if ($inplace) {
            rename($dstPath, $srcPath);
        }


    }

    /**
     * @return NodeTraverser
     */
    public function getTraverser()
    {
        $traverser = new NodeTraverser();

        // Find Path
        $reflector = new \ReflectionObject($this);
        $path = $reflector->getFileName();
        $path = dirname($path);

        // Generate FQCN
        $fqcnPath = get_class($this);
        $fqcnPath = explode('\\', $fqcnPath);
        $fqcnPath[count($fqcnPath) - 1] = 'Visitors';
        $fqcnPath = implode('\\', $fqcnPath);

        // Iterate node visitors and add them to the traverser
        foreach (new \FilesystemIterator($path.'/Visitors', \FilesystemIterator::SKIP_DOTS) as $fileInfo) {
            /* @var \SplFileInfo $fileInfo */
            $class = str_replace('.php', '', $fileInfo->getFilename());
            $fqcn = $fqcnPath.'\\'.$class;
            $this->getIo()->debug('Loading visitor: '.$fqcn, 'trns');

            $traverser->addVisitor(new $fqcn());
        }

        return $traverser;
    }
}
