<?php namespace ProcessWire;

include('vendor/autoload.php');

use PhpParser\NodeDumper;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\ParentConnectingVisitor;
use PhpParser\ParserFactory;

/**
 * PHP Api Documentation Generator
 *
 * This is a helper class. It does not render the final documentation,
 * but parses the source and comments to build a slim tree with all
 * information.
 *
 * Ideally, this tree is used together with a templating system.
 *
 * Usage:
 * ```php
 * // Create a generator
 * $generator = new ModuleApiDocGenerator();
 *
 * // Parse source file, e.g. this classes file itself:
 * $generator->parse("./ModuleApiDocGenerator.php');
 *
 * // Generate class documentation tree:
 * $classes = $generator->buildDoc();
 * ```
 *
 * The generated tree is an associative array with the following structure:
 *
 * ```php
 * [
 *   "classname" => [
 *     "namespace"       => string,
 *     "comment"         => string,		// The original comment, unformatted
 *
 *     // The phpdoc information extracted from the comment
 *     "parsedComment"   => Array,      // Parsed phpdoc information, see further down
 *
 *     // Information about methods, parsed directly from the source
 *     "methods"       => [
 *       "METHODNAME"    => [
 *         "name"          => string,
 *         "type"          => string, // Return type if declared in the source
 *         "visibility"    => string, // public/protected/private
 *         "scope"         => string, // empty or "static"
 *         // This is the arguments definition parsed from the source, not from
 *         // the phpdoc comment:
 *         "params"        => [[
 *           "type"          => string,
 *           "name"          => string,
 *           "default"       => string
 *         ], ...],
 *         "comment"       => string, // The original comment, unformatted,
 *         "parsedComment" => Array   // Parsed phpdoc information, see further down
 *       ],
 *       ...
 *     ]
 *   ],
 *   ...
 * ];
 * ```
 *
 * Each parsed comment (for class, method, property) has this structure:
 *
 * ```php
 * [
 *   "summary"         => string,	// first line of the comment, the summary line
 *   "description"     => string,   // further lines in the comment, the description part
 *   // Return value documented with @eturn, may be null:
 *   "returns"         => [
 *     "type"            => string,
 *     "text"            => string, // Return value description, may be empty
 *   ],
 *   // Arguments documented with @param
 *   "params"         => [
 *     "type"           => string, // Argument type, may be empty
 *     "name"           => string, // Argument name, may be empty
 *     "text"			=> string, // Return value description, may be empty
 *   ],
 *   "todo"          => [string, ...]
 * ];
 * ```
 */
class ModuleApiDocGenerator {

	protected $classes = [];

	protected $filename = '';
	
	protected $ast;

	/**
	 * Constructor
	 *
	 * @param string $file Pass the full path to the PHP file you want to document
	 * @return ModuleApiDocGenerator $this
	 */
	public function __construct(string $file) {
		$this->filename = $file;
	}
	
	/**
	 * The parse function
	 *
	 * Parses the source code and creates an abstract syntax tree (AST).
	 *
	 * @return array The AST
	 */
	public function parse() {
		$traverser = new NodeTraverser;
		$traverser->addVisitor(new ParentConnectingVisitor);
		
		$code = file_get_contents($this->filename);

		$parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7);
		$this->ast    = $parser->parse($code);
		
		return $this->ast;
	}

	/**
	 * Build the documentation tree.
	 *
	 * You need to run parse() first!
	 *
	 * @return array The documentation tree
	 */
	public function buildDoc() {
		
		if(! $this->ast) {
			return [
				"error"		=>	"You need to run parse() before buildDoc()!"
			];
		}
		
		foreach($this->ast as $ast) {
			if($this->ast[0] instanceof \PhpParser\Node\Stmt\Namespace_) {
				$ns = implode('\\', $ast->name->parts);
				$nsComment = $this->getComment($ast);
				$nsParsedComment = $this->parseComment($nsComment, true);
				$startNode = $ast->stmts;
			} else {
				$ns = '';
				$startNode = [$ast];
				$nsComment = '';
				$nsParsedComment = [];
			}

			foreach($startNode as $node) {
				if($node instanceof \PhpParser\Node\Stmt\Class_) {
					$this->classes[$node->name->name] = $this->buildClassDoc($ns, $node);
				}
			}
		}

		if(count($this->classes) === 0) {
			$this->classes["NOCLASS"] = $this->buildFunctionDoc($ns, $startNode, $nsComment, $nsParsedComment);
		}
		
		return $this->classes;
	}
	
	protected function buildFunctionDoc($ns, $root, $nsComment, $nsParsedComment) {
		$functions = [];
		
		foreach($root as $node) {
			if($node instanceof \PhpParser\Node\Stmt\Function_) {
				$comment = $this->getComment($node);
				$parsedComment = $this->parseComment($comment, true);
				$func = $this->getFunction($node);
				$functions[$func['name']] = $func;
			}
		}
		
		return [
			"namespace"		=>	$ns,
			"functions"		=>	$functions,
			"comment"		=>	$nsComment,
			"parsedComment"	=>	$nsParsedComment
		];
	}
	
	/**
	 * Create the documentation tree for the current class.
	 *
	 * There can be multiple classes in one file.
	 */
	protected function buildClassDoc($ns, $clsNode) {
		$comment = $this->getComment($clsNode);
		$parsedComment = $this->parseComment($comment, true);
		$properties = [];
		$methods = [];
		
		foreach($clsNode->stmts as $item) {
			if($item instanceof \PhpParser\Node\Stmt\Property) {
				$visibility = $this->getVisibility($item);
				$scope = $this->getScope($item);
				foreach($item->props as $propNode) {
					$prop = $this->getProperty($propNode);
					$prop["visibility"] = $visibility;
					$prop["scope"] = $scope;
					$properties[$prop['name']] = $prop;
				}
			} elseif($item instanceof \PhpParser\Node\Stmt\ClassMethod) {
				$meth = $this->getMethod($item);
				$methods[$meth['name']] = $meth;
			}
		}
		
		uasort($methods, function($a, $b) {
			return
				$b['name'] === '__construct' ?
				1 :
				strnatcmp($b["scope"], $a["scope"]) | strnatcmp($a["name"], $b["name"]);
		});

		uasort($properties, function($a, $b) {
			return strnatcmp($a["name"], $b["name"]);
		});
		
		return [
			"namespace"		=>	$ns,
			"comment" 		=>	$comment,
			"parsedComment"	=>	$parsedComment,
			"properties"	=>	$properties,
			"methods"		=>	$methods
		];
	}
	
	protected function getComment($node) {
		$comments = $node->getAttribute('comments');
		$comment = (is_array($comments) && count($comments) > 0) ? $comments[count($comments) - 1]->getText() : '';
		
		return $comment;
	}
	
	protected function parseComment($comment, $withDefinitions = false) {
		
		if(! $this->isDocComment($comment)) return '';
		
		$defs = [
			"returns"		=>	false,
			"params"		=>	[],
			"todo"			=>	null
		];
		
		$stripped = preg_replace('~^\\s+\\* ~m', '', $comment);
		
		$lines = array_slice(preg_split('~[\\r\\n]+~', $stripped, -1, 0), 1, -1);
		for($i = 0; $i < count($lines); $i++) {
			$lines[$i] = preg_replace('~^\\s+\\*\\s*$~', '', $lines[$i]);
		}
		
		$this->removeSpecialPwDocComments($lines);
		$this->forwardLinesToNonEmpty($lines);
		
		if(count($lines) > 0) {
			$summary = array_shift($lines);
		}
		
		if($withDefinitions)
			$defs = $this->extractDefinitionsFromComment($lines);
		else
			$this->removeDefinitionsFromComment($lines);
		
		$this->forwardLinesToNonEmpty($lines);
		$this->trimTrailingLinesToNonEmpty($lines);
		
		$ret = [
			"summary"		=>	$summary,
			"returns"		=>	$defs["returns"],
			"params"		=>	$defs["params"],
			"description"	=>	implode("\n", $lines),
			"todo"			=>	$defs["todo"]
		];
		
		return $ret;
	}
	
	protected function forwardLinesToNonEmpty(&$lines) {
		while(count($lines) > 0 && $lines[0] === '') {
			array_shift($lines);
		}
	}
	
	protected function trimTrailingLinesToNonEmpty(&$lines) {
		while(count($lines) > 0 && $lines[count($lines)-1] === '') {
			array_pop($lines);
		}
	}
	
	protected function removeSpecialPwDocComments(&$lines) {
		$outlines = [];
		foreach($lines as $line) {
			if(! preg_match('/^\\s*#pw-/', $line))
				$outlines[] = $line;
		}
		
		array_splice($lines, 0, count($lines), $outlines);
	}
	
	protected function extractDefinitionsFromComment(&$lines) {
		$params = [];
		$returns = false;
		$outlines = [];
		$todos = [];
		foreach($lines as $line) {
			if(preg_match('/^\\s*@param (\\S+)(?:\\s+)?(\\$\\S+)?(?:\\s+)?(.*)?$/', $line, $match)) {
				$params[] = [
					"type"		=>	$match[1],
					"name"		=>	$match[2],
					"text"		=>	$match[3]
				];
			} elseif(preg_match('/^\\s*@return\\s+(\\S+)(?:\\s+)?(.*)?$/', $line, $match)) {
				$returns = [
					"type"		=> $match[1],
					"text"		=> $match[2]
				];
			} elseif(preg_match('/^\\s*@todo:?\\s?(.*)?$/', $line, $match)) {
				$todos[] = $match[1];
			} elseif(preg_match('/^\\s*@/', $line)) {
				// Do nothing, strip definitions we don't recognize
			} elseif(preg_match('/^\\s*FIXED\\s.*$/', $line)) {
				// Do nothing, strip Ryan's info about fixed issues
			} else {
				$outlines[] = $line;
			}
		}
		
		array_splice($lines, 0, count($lines), $outlines);
		
		return [
			"params"		=>	$params,
			"returns"		=>	$returns,
			"todo"			=>	$todos
		];
	}

	protected function removeDefinitionsFromComment(&$lines) {
		$outlines = [];
		foreach($lines as $line) {
			if(!preg_match('/\\s*@/', $line))
				$outlines[] = $line;
		}
		array_splice($lines, 0, count($lines), $outlines);
	}
	
	protected function isDocComment($comment) {
		return preg_match('~^\s*/\\*{2}~s', $comment);
	}
	
	protected function getProperty($node) {
		$name = $node->name->name;
		$default = '';
		if($node->default) {
			if(property_exists($node->default, 'value'))
				$default = isset($node->default->value) ? $node->default->value : "''";
			else {
				$default = preg_replace('/^.*\\\\(.*?)_/', '$1', get_class($node->default));
			}
		}
		
		$comment = $this->getComment($node);
		$parsedComment = $this->parseComment($comment);
		
		return [
			"name"			=>	$name,
			"default"		=>	$default,
			"comment"		=>	$comment,
			"parsedComment"	=>	$parsedComment
		];
	}
	
	protected function getMethod($node) {
		$name = $node->name->name;
		$type = $node->returnType ? $node->returnType->name : '';
		$params = $this->getParams($node->params);
		
		$comment = $this->getComment($node);
		$parsedComment = $this->parseComment($comment, true);
		
		return [
			"name"			=>	$name,
			"type"			=>	$type,
			"visibility"	=>	$this->getVisibility($node),
			"scope"			=>	$this->getScope($node),
			"params"		=>	$params,
			"comment"		=>	$comment,
			"parsedComment"	=>	$parsedComment
		];
	}
	
	protected function getFunction($node) {
		$name = $node->name->name;
		$type = $node->returnType ? $node->returnType->name : '';
		$params = $this->getParams($node->params);

		$comment = $this->getComment($node);
		$parsedComment = $this->parseComment($comment, true);
		
		return [
			"name"			=>	$name,
			"type"			=>	$type,
			"params"		=>	$params,
			"comment"		=>	$comment,
			"parsedComment"	=>	$parsedComment
		];
		
	}
	
	protected function getParams($paramsArr) {
		$params = [];
		if(!is_array($paramsArr)) return $params;
		
		foreach($paramsArr as $prm) {
			$type = $prm->type && property_exists($prm->type, 'name') ? $prm->type->name : '';
			$name = $prm->var->name;
			$default = $prm->default && property_exists($prm->default, 'name')  && property_exists($prm->default->name, 'parts') ? $prm->default->name->parts[0] : /*json_encode($prm->default)*/ '';
			$params[] = [
				"type"		=>	$type,
				"name"		=>	$name,
				"default"	=>	$default
			];
		}
		
		return $params;
	}

	protected function getVisibility($node) {
		if($node->flags & 1)
			$visibility = 'public';
		elseif($node->flags & 2)
			$visibility = 'protected';
		elseif($node->flags & 4)
			$visibility = 'private';
		else
			$visibility = $node->flags;
		
		return $visibility;
	}

	protected function getScope($node) {
		return $node->flags & 8 ? "static" : "";
	}

	/**
	 * Dump the AST
	 *
	 * Only for debugging purposes.
	 *
	 * @return string
	 */
	public function dump() {
		return $dumper->dump($this->ast);
	}
	
}
