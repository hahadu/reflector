<?php
/**
 *
 */
namespace Hahadu\Reflector;

use Reflector;
use Hahadu\Reflector\Reflection\{Tag,Context,Location,Description};
use LogicException;
/**
 * 解析Reflector结构.
 *
 */
class Reflection implements Reflector
{
    /** @var string The opening line for this docblock. */
    protected $short_description = '';

    /**
     * @var Description The actual
     *     description for this docblock.
     */
    protected $long_description = null;

    /**
     * @var Tag[] An array containing all
     *     the tags in this docblock; except inline.
     */
    protected $tags = [];

    /** @var Context Reflection上下文的信息. */
    protected $context = null;

    /** @var Location 反射的位置信息. */
    protected $location = null;

    /** @var bool 如果这个反射(开始)是一个模板 */
    protected $isTemplateStart = false;

    /** @var bool Does this Reflection signify the end of a Reflection template? */
    protected $isTemplateEnd = false;

    /**
     * 解析给定的docblock并填充成员字段.
     *
     * 构造函数也可以接收命名空间信息，例如
     * 当前命名空间和别名。这个信息被一些标签使用
     *      (e.g.
     * @param Reflector|string $docblock A docblock comment (including
     *     asterisks) or reflector supporting the getDocComment method.
     * @param Context|null $context The context in which the Reflection
     *     occurs.
     * @param Location|null $location The location within the file that this
     *     Reflection occurs in.
     */
    public function __construct(
        $docblock,
        Context $context = null,
        Location $location = null
    ) {
        if (is_object($docblock)) {
            throw_if(!method_exists($docblock, 'getDocComment'),
                \InvalidArgumentException::class,
                '传递的对象无效:给定的Reflection必须支持getDocComment方法'
            );

            $docblock = $docblock->getDocComment();
        }

        $docblock = $this->cleanInput($docblock);

        list($templateMarker, $short, $long, $tags) = $this->splitDocBlock($docblock);
        $this->isTemplateStart = $templateMarker === '#@+';
        $this->isTemplateEnd = $templateMarker === '#@-';
        $this->short_description = $short;
        $this->long_description = new Description($long, $this);
        $this->parseTags($tags);


        $this->context  = $context;
        $this->location = $location;
    }

    /**
     * 从DocBlock注释中去掉星号.
     *
     * @param string $comment String containing the comment text.
     *
     * @return string
     */
    protected function cleanInput($comment)
    {
        $comment = trim(
            preg_replace(
                '#[ \t]*(?:\/\*\*|\*\/|\*)?[ \t]{0,1}(.*)?#u',
                '$1',
                $comment
            )
        );

        // reg ex above is not able to remove */ from a single line docblock
        if (substr($comment, -2) == '*/') {
            $comment = trim(substr($comment, 0, -2));
        }

        // normalize strings
        $comment = str_replace(["\r\n", "\r"], "\n", $comment);

        return $comment;
    }

    /**
     * 将反射信息分割为模板标记、摘要、描述和标记块。
     *
     * @param string $comment 拆分注释.
     *
     * @author Richard van Velzen (@_richardJ) Special thanks to Richard for the regex responsible for the split.
     * @author Mike van Riel <me@mikevanriel.com> for extending the regex with template marker support.
     *
     * @return string[] 包含模板标记(如果有的话)、摘要、描述和包含标记的字符串。
     */
    protected function splitDocBlock($comment)
    {
        // 提高性能的技巧:如果第一个字符是@，那么这个Reflection中只有标记。
        // 此方法不分割标签，因此我们将其作为第四个结果(标签)返回。
        // 节省了运行正则表达式对性能的影响
        if (strpos($comment, '@') === 0) {
            return ['', '', '', $comment];
        }

        // ：从行结束处清除所有额外的水平空格以防止解析问题
        $comment = preg_replace('/\h*$/Sum', '', $comment);

        /*
         * 将文档块分割为模板标记、短描述、长描述和标记部分
         *
         * - 模板标记是空的，#@+或#@-如果反射以这两个标记中的任何一个开始(它后面可能出现换行符，并将被剥离)
         * - 简短的描述从第一个字符开始，直到遇到一个点，后跟一个换行符或两个连续的换行符(考虑空格错误时考虑水平空格)。可选
         * - 长描述，任何字符，直到遇到一个新行，后跟一个@和单词字符(标签)。可选
         * - 标签、剩余的字符
         *
         * Big thanks to RichardJ for contributing this Regular Expression
         */
        preg_match(
            '/
            \A
            # 1. Extract the template marker
            (?:(\#\@\+|\#\@\-)\n?)?

            # 2. Extract the summary
            (?:
              (?! @\pL ) # The summary may not start with an @
              (
                [^\n.]+
                (?:
                  (?! \. \n | \n{2} )     # End summary upon a dot followed by newline or two newlines
                  [\n.] (?! [ \t]* @\pL ) # End summary when an @ is found as first character on a new line
                  [^\n.]+                 # Include anything else
                )*
                \.?
              )?
            )

            # 3. Extract the description
            (?:
              \s*        # Some form of whitespace _must_ precede a description because a summary must be there
              (?! @\pL ) # The description may not start with an @
              (
                [^\n]+
                (?: \n+
                  (?! [ \t]* @\pL ) # End description when an @ is found as first character on a new line
                  [^\n]+            # Include anything else
                )*
              )
            )?

            # 4. Extract the tags (anything that follows)
            (\s+ [\s\S]*)? # everything that follows
            /ux',
            $comment,
            $matches
        );
        array_shift($matches);

        while (count($matches) < 4) {
            $matches[] = '';
        }

        return $matches;
    }

    /**
     * 创建标记对象。
     *
     * @param string $tags Tag block to parse.
     *
     * @return void
     * @throw LogicException
     */
    protected function parseTags($tags)
    {
        $result = [];
        $tags = trim($tags);
        if ('' !== $tags) {

            throw_if('@' !== $tags[0],LogicException::class,
                'tag block无效,tag block 应该以文本而不是实际标记开始：' . $tags);
            foreach (explode("\n", $tags) as $tag_line) {
                if (isset($tag_line[0]) && ($tag_line[0] === '@')) {
                    $result[] = $tag_line;
                } else {
                    $result[count($result) - 1] .= "\n" . $tag_line;
                }
            }

            // create proper Tag objects
            foreach ($result as $key => $tag_line) {
                $result[$key] = Tag::createInstance(trim($tag_line), $this);
            }
        }

        $this->tags = $result;
    }

    /**
     * 获取文档块的文本部分。
     *
     * 获取文档块的文本部分(短描述和长描述组合)。
     *
     * @return string 文档块的文本部分。
     */
    public function getText()
    {
        $short = $this->getShortDescription();
        $long = $this->getLongDescription()->getContents();

        if ($long) {
            return $short.
                PHP_EOL.
                PHP_EOL.
                $long;

        } else {
            return $short;
        }
    }

    /**
     * 设置文档块的文本部分。
     *
     * 设置文档块的文本部分(短描述和长描述的组合)。
     *
     * @param string $comment 文档块的新文本部分
     *
     * @return $this This doc block.
     */
    public function setText(string $comment)
    {
        list(,$short, $long) = $this->splitDocBlock($comment);
        $this->short_description = $short;
        $this->long_description = new Description($long, $this);
        return $this;
    }
    /**
     * Returns the opening line or also known as short description.
     *
     * @return string
     */
    public function getShortDescription()
    {
        return $this->short_description;
    }

    /**
     * Returns the full description or also known as long description.
     *
     * @return Description
     */
    public function getLongDescription()
    {
        return $this->long_description;
    }

    /**
     * 返回此反射是否为模板节的开始。
     *
     *Docblock可以用作一系列后续Docblock的模板。这由一个特殊的标记表示
     *（`#@+`），直接附加在反射的开头`/**`之后。
     *
     * 一个简单的例子：
     *
     * ```
     * /**#@+
     *  * My Reflection
     *  * /
     * ```
     *
     *说明和标记（不是摘要！）复制到所有后续DocBlock上，并应用于所有DocBlock元素，直到找到另一个包含结束标记（`#@-`）的反射。
     *
     * @return boolean
     * @see $this::isTemplateEnd() 用于检查是否提供了关闭标记。
     *
     */
    public function isTemplateStart()
    {
        return $this->isTemplateStart;
    }

    /**
     * 返回该反射是否为模板部分的结束
     *
     * @see self::isTemplateStart() 有关模板功能的更完整说明。
     *
     * @return boolean
     */
    public function isTemplateEnd()
    {
        return $this->isTemplateEnd;
    }

    /**
     * 当前上下文.
     *
     * @return Context
     */
    public function getContext()
    {
        return $this->context;
    }

    /**
     * 当前位置.
     *
     * @return Location
     */
    public function getLocation()
    {
        return $this->location;
    }

    /**
     * 当前反射标记.
     *
     * @return Tag[]
     */
    public function getTags()
    {
        return $this->tags;
    }

    /**
     * 返回与给定名称匹配的标记数组。
     *
     * 如果未找到标记，则返回空数组。
     *
     * @param string $name String to search by.
     *
     * @return Tag[]
     */
    public function getTagsByName($name)
    {
        $result = [];

        /** @var Tag $tag */
        foreach ($this->getTags() as $tag) {
            if ($tag->getName() != $name) {
                continue;
            }

            $result[] = $tag;
        }

        return $result;
    }

    /**
     * Checks if a tag of a certain type is present in this Reflection.
     *
     * @param string $name Tag name to check for.
     *
     * @return bool
     */
    public function hasTag($name)
    {
        /** @var Tag $tag */
        foreach ($this->getTags() as $tag) {
            if ($tag->getName() == $name) {
                return true;
            }
        }

        return false;
    }

    /**
     * 在标记列表的末尾添加标记。
     *
     * @param Tag $tag The tag to add.
     *
     * @return Tag The newly added tag.
     *
     * @throws \LogicException When the tag belongs to a different Reflection.
     */
    public function appendTag(Tag $tag)
    {
        if (null === $tag->getDocBlock()) {
            $tag->setDocBlock($this);
        }


        if ($tag->getDocBlock() === $this) {
            $this->tags[] = $tag;
        } else {
            throw new \LogicException(
                'This tag belongs to a different Reflection object.'
            );
        }

        return $tag;
    }


    /**
     * Builds a string representation of this object.
     *
     * @todo determine the exact format as used by PHP Reflection and
     *     implement it.
     *
     * @return string
     * @codeCoverageIgnore Not yet implemented
     */
    public static function export()
    {
        throw new \Exception('Not yet implemented');
    }

    /**
     * Returns the exported information (we should use the export static method
     * BUT this throws an exception at this point).
     *
     * @return string
     * @codeCoverageIgnore Not yet implemented
     */
    public function __toString()
    {
        return 'Not yet implemented';
    }
}
