<?php

namespace Hahadu\Reflector\Reflection;

/**
 * 反射内容的上下文
 */
class Context
{
    /** @var string 当前 namespace. */
    protected $namespace = '';

    /** @var array namespace 别名列表 => Fully Qualified Namespace. */
    protected $namespace_aliases = [];

    /** @var string 名称空间中结构元素名称. */
    protected $lsen = '';

    /**
     * 创建一个新的context.
     * @param string $namespace         反射所在的namespace
     *     resides in.
     * @param array  $namespace_aliases namespace 别名列表 => Fully
     *     Qualified Namespace.
     * @param string $lsen              Name of the structural element, within
     *     the namespace.
     */
    public function __construct(
        string $namespace = '',
        array  $namespace_aliases = [],
        string $lsen = ''
    ) {
        if (!empty($namespace)) {
            $this->setNamespace($namespace);
        }
        $this->setNamespaceAliases($namespace_aliases);
        $this->setLSEN($lsen);
    }

    /**
     * @return string Reflection 所属的 namespace
     */
    public function getNamespace(): string
    {
        return $this->namespace;
    }

    /**
     * @return array List of namespace aliases => Fully Qualified Namespace.
     */
    public function getNamespaceAliases(): array
    {
        return $this->namespace_aliases;
    }

    /**
     * 局部元素名称。.
     *
     * @return string Name of the structural element, within the namespace.
     */
    public function getLSEN()
    {
        return $this->lsen;
    }

    /**
     * 设置新的名称空间。
    ＊
     * 为上下文设置一个新的namespace。自动裁掉两端斜杠，关键字“global”和“default”被视为无namespace的别名
     *
     * @param string $namespace The new namespace to set.
     *
     * @return $this
     */
    public function setNamespace($namespace)
    {
        if ('global' !== $namespace
            && 'default' !== $namespace
        ) {
            // Srip leading and trailing slash
            $this->namespace = trim((string)$namespace, '\\');
        } else {
            $this->namespace = '';
        }
        return $this;
    }

    /**
     * 设置namespace别名，替换前面的所有aliases
     *
     * @param array $namespace_aliases List of namespace aliases => Fully
     *     Qualified Namespace.
     *
     * @return $this
     */
    public function setNamespaceAliases(array $namespace_aliases)
    {
        $this->namespace_aliases = array();
        foreach ($namespace_aliases as $alias => $fqnn) {
            $this->setNamespaceAlias($alias, $fqnn);
        }
        return $this;
    }

    /**
     * Adds a namespace alias to the context.
     *
     * @param string $alias The alias name (the part after "as", or the last
     *     part of the Fully Qualified Namespace Name) to add.
     * @param string $fqnn  The Fully Qualified Namespace Name for this alias.
     *     Any form of leading/trailing slashes are accepted, but what will be
     *     stored is a name, prefixed with a slash, and no trailing slash.
     *
     * @return $this
     */
    public function setNamespaceAlias($alias, $fqnn)
    {
        $this->namespace_aliases[$alias] = '\\' . trim((string)$fqnn, '\\');
        return $this;
    }

    /**
     * 设置新的局部结构图元名称。
     *
     * 设置新的局部结构 element 。局部名称还包含确定结构元素类型的标点符号（例如，函数和方法的尾随“（“ and ”）”。
     *
     * @param string $lsen The new local name of a structural element.
     *
     * @return $this
     */
    public function setLSEN($lsen)
    {
        $this->lsen = (string)$lsen;
        return $this;
    }
}
