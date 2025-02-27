<?php
/**
 * Provides functionality for XML files.
 *
 * This class is implemented to use the UTF-8 character encoding. Please see
 * http://flourishlib.com/docs/UTF-8 for more information.
 *
 * @copyright  Copyright (c) 2007-2010 Will Bond, others
 * @author     Will Bond [wb] <will@flourishlib.com>
 * @author     Craig Ruksznis [cr-imarc] <craigruk@imarc.net>
 * @license    http://flourishlib.com/license
 *
 * @see       http://flourishlib.com/fXML
 *
 * @version    1.0.0b6
 * @changes    1.0.0b6  Updated class to use fCore::startErrorCapture() instead of `error_reporting()` [wb, 2010-08-09]
 * @changes    1.0.0b5  Added the `$fix_entities_encoding` parameter to ::__construct() [cr-imarc+wb, 2010-08-08]
 * @changes    1.0.0b4  Updated the class to automatically add a `__` prefix for the default namespace and to use that for attribute and child element access [wb, 2010-04-06]
 * @changes    1.0.0b3  Added the `$http_timeout` parameter to ::__construct() [wb, 2009-09-16]
 * @changes    1.0.0b2  Added instance functionality for reading of XML files [wb, 2009-09-01]
 * @changes    1.0.0b   The initial implementation [wb, 2008-01-13]
 */
class fXML implements ArrayAccess
{
    // The following constants allow for nice looking callbacks to static methods
    public const encode = 'fXML::encode';

    public const sendHeader = 'fXML::sendHeader';

    /**
     * Custom prefix => namespace URI mappings.
     *
     * @var array
     */
    protected $__custom_prefixes;

    /**
     * The dom element for this XML.
     *
     * @var DOMElement
     */
    protected $__dom;

    /**
     * An XPath object for performing xpath lookups.
     *
     * @var DOMXPath
     */
    protected $__xpath;

    /**
     * The XML string for serialization.
     *
     * @var string
     */
    protected $__xml;

    /**
     * Create the XML object from a string, fFile or URL.
     *
     * The `$default_namespace` will be used for any sort of methods calls,
     * member access or array access when the element or attribute name does
     * not include a `:`.
     *
     * @param fFile|string $source                The source of the XML, either an fFile object, a string of XML, a file path or a URL
     * @param numeric      $http_timeout          The timeout to use in seconds when requesting an XML file from a URL
     * @param bool         $fix_entities_encoding This will fix two common XML authoring errors and should only be used when experiencing decoding issues - HTML entities that haven't been encoded as XML, and XML content published in ISO-8859-1 or Windows-1252 encoding without an explicit encoding attribute
     * @param  fFile|string  :$source
     * @param  bool       :$fix_entities_encoding
     *
     * @throws fValidationException When the source XML is invalid or does not exist
     *
     * @return fXML
     */
    public function __construct($source, $http_timeout = null, $fix_entities_encoding = null)
    {
        if (is_bool($http_timeout)) {
            $fix_entities_encoding = $http_timeout;
            $http_timeout = null;
        }

        // Prevent spitting out errors to we can throw exceptions
        $old_setting = libxml_use_internal_errors(true);

        $exception_message = null;

        try {
            if ($source instanceof fFile && $fix_entities_encoding) {
                $source = $source->read();
            }

            if ($source instanceof DOMElement) {
                $this->__dom = $source;
                $xml = true;
            } elseif ($source instanceof fFile) {
                $xml = simplexml_load_file($source->getPath());

            // This handles URLs specially by adding a reasonable timeout
            } elseif (preg_match('#^(?P<protocol>http(s)?)://#', $source, $matches)) {
                if ($http_timeout === null) {
                    $http_timeout = ini_get('default_socket_timeout');
                }

                // We use the appropriate protocol here so PHP can supress IIS https:// warnings
                $context = stream_context_create([
                    $matches['protocol'] => ['timeout' => $http_timeout],
                ]);

                // If the URL is not loaded in time, this supresses the file_get_contents() warning
                fCore::startErrorCapture(E_WARNING);
                $xml = file_get_contents($source, 0, $context);
                fCore::stopErrorCapture();

                if (! $xml) {
                    throw new fExpectedException('The URL specified, %s, could not be loaded', $source);
                }

                if ($fix_entities_encoding) {
                    $xml = $this->fixEntitiesEncoding($xml);
                }

                $xml = new SimpleXMLElement($xml);
            } else {
                $is_path = $source && ! preg_match('#^\s*<#', $source);

                if ($fix_entities_encoding) {
                    if ($is_path) {
                        $source = file_get_contents($source);
                        $is_path = false;
                    }
                    $source = $this->fixEntitiesEncoding($source);
                }

                $xml = new SimpleXMLElement($source, 0, $is_path);
            }
        } catch (Exception $e) {
            $exception_message = $e->getMessage();
            $xml = false;
        }

        // We want it to be clear when XML parsing issues occur
        if ($xml === false) {
            $errors = libxml_get_errors();
            foreach ($errors as $error) {
                $exception_message .= "\n".rtrim($error->message);
            }
            // If internal errors were off before, turn them back off
            if (! $old_setting) {
                libxml_use_internal_errors(false);
            }

            throw new fValidationException(str_replace('%', '%%', $exception_message));
        }

        if (! $old_setting) {
            libxml_use_internal_errors(false);
        }

        if (! $this->__dom) {
            $this->__dom = dom_import_simplexml($xml);
        }

        if ($this->__dom->namespaceURI && $this->__dom->prefix == '') {
            $this->addCustomPrefix('__', $this->__dom->namespaceURI);
        }
    }

    /**
     * Allows access to the text content of a child tag.
     *
     * The child element name (`$name`) may start with a namespace prefix and a
     * `:` to indicate what namespace it is part of. A blank namespace prefix
     * (i.e. an element name starting with `:`) is treated as the XML default
     * namespace.
     *
     * @internal
     *
     * @param string $name The child element to retrieve
     *
     * @return null|fXML The child element requested
     */
    public function __get($name)
    {
        // Handle nice callback syntax
        static $methods = [
            '__construct' => true,
            '__get' => true,
            '__isset' => true,
            '__sleep' => true,
            '__toString' => true,
            '__wakeup' => true,
            'addCustomPrefix' => true,
            'getName' => true,
            'getNamespace' => true,
            'getPrefix' => true,
            'getText' => true,
            'offsetExists' => true,
            'offsetGet' => true,
            'offsetSet' => true,
            'offsetUnset' => true,
            'toXML' => true,
            'xpath' => true,
        ];

        if (isset($methods[$name])) {
            return [$this, $name];
        }

        if ($this->__dom->namespaceURI && $this->__dom->prefix == '' && strpos($name, ':') === false) {
            $name = '__:'.$name;
        }
        $first_child = $this->query($name.'[1]');
        if ($first_child->length) {
            return $first_child->item(0)->textContent;
        }
    }

    /**
     * The child element name (`$name`) may start with a namespace prefix and a
     * `:` to indicate what namespace it is part of. A blank namespace prefix
     * (i.e. an element name starting with `:`) is treated as the XML default
     * namespace.
     *
     * @internal
     *
     * @param string $name The child element to check - see method description for details about namespaces
     *
     * @return bool If the child element is set
     */
    public function __isset($name)
    {
        if ($this->__dom->namespaceURI && $this->__dom->prefix == '' && strpos($name, ':') === false) {
            $name = '__:'.$name;
        }

        return (bool) $this->query($name.'[1]')->length;
    }

    /**
     * Prevents users from trying to set elements.
     *
     * @internal
     *
     * @param string $name  The element to set
     * @param mixed  $value The value to set
     */
    public function __set($name, $value)
    {
        throw new fProgrammerException('The %s class does not support modifying XML', __CLASS__);
    }

    /**
     * The XML needs to be made into a string before being serialized.
     *
     * @internal
     *
     * @return array The members to serialize
     */
    public function __sleep()
    {
        $this->__xml = $this->toXML();

        return ['__custom_prefixes', '__xml'];
    }

    /**
     * Gets the string inside the root XML element.
     *
     * @return string The text inside the root element
     */
    public function __toString()
    {
        return (string) $this->__dom->textContent;
    }

    /**
     * Prevents users from trying to unset elements.
     *
     * @internal
     *
     * @param string $name The element to unset
     */
    public function __unset($name)
    {
        throw new fProgrammerException('The %s class does not support modifying XML', __CLASS__);
    }

    /**
     * The XML needs to be made into a DOMElement when woken up.
     *
     * @internal
     */
    public function __wakeup()
    {
        $this->__dom = dom_import_simplexml(new SimpleXMLElement($this->__xml));
        $this->__xml = null;
    }

    /**
     * Encodes content for display in a UTF-8 encoded XML document.
     *
     * @param string $content The content to encode
     *
     * @return string The encoded content
     */
    public static function encode($content)
    {
        return htmlspecialchars(html_entity_decode($content, ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8');
    }

    /**
     * Sets the proper `Content-Type` HTTP header for a UTF-8 XML file.
     */
    public static function sendHeader(): void
    {
        header('Content-Type: text/xml; charset=utf-8');
    }

    /**
     * Adds a custom namespace prefix to full namespace mapping.
     *
     * This namespace prefix will be valid for any operation on this object,
     * including calls to ::xpath().
     *
     * @param string $ns_prefix The custom namespace prefix
     * @param string $namespace The full namespace it maps to
     */
    public function addCustomPrefix($ns_prefix, $namespace): void
    {
        if (! $this->__custom_prefixes) {
            $this->__custom_prefixes = [];
        }
        $this->__custom_prefixes[$ns_prefix] = $namespace;
        if ($this->__xpath) {
            $this->__xpath->registerNamespace($ns_prefix, $namespace);
        }
    }

    /**
     * Returns the name of the current element.
     *
     * @return string The name of the current element
     */
    public function getName()
    {
        return $this->__dom->localName;
    }

    /**
     * Returns the namespace of the current element.
     *
     * @return null|string The namespace of the current element
     */
    public function getNamespace(): string|null
    {
        return $this->__dom->namespaceURI;
    }

    /**
     * Returns the namespace prefix of the current element.
     *
     * @return string The namespace prefix of the current element
     */
    public function getPrefix()
    {
        return $this->__dom->prefix;
    }

    /**
     * Returns the string text of the current element.
     *
     * @return string The string text of the current element
     */
    public function getText()
    {
        return (string) $this->__dom->textContent;
    }

    /**
     * Provides functionality for isset() and empty() (required by arrayaccess interface).
     *
     * Offsets refers to an attribute name. Attribute may start with a namespace
     * prefix and a `:` to indicate what namespace the attribute is part of. A
     * blank namespace prefix (i.e. an offset starting with `:`) is treated as
     * the XML default namespace.
     *
     * @internal
     *
     * @param string $offset The offset to check
     *
     * @return bool If the offset exists
     */
    public function offsetExists($offset): bool
    {
        return (bool) $this->query('@'.$offset.'[1]')->length;
    }

    /**
     * Provides functionality for get [index] syntax (required by ArrayAccess interface).
     *
     * Offsets refers to an attribute name. Attribute may start with a namespace
     * prefix and a `:` to indicate what namespace the attribute is part of. A
     * blank namespace prefix (i.e. an offset starting with `:`) is treated as
     * the XML default namespace.
     *
     * @internal
     *
     * @param string $offset The attribute to retrieve the value for
     *
     * @return string The value of the offset
     */
    public function offsetGet($offset): mixed
    {
        $attribute = $this->query('@'.$offset.'[1]');
        if ($attribute->length) {
            return $attribute->item(0)->nodeValue;
        }

        return null;
    }

    /**
     * Required by ArrayAccess interface.
     *
     * @internal
     *
     * @param int|string $offset The offset to set
     * @param mixed      $value
     */
    public function offsetSet($offset, $value): void
    {
        throw new fProgrammerException('The %s class does not support modifying XML', __CLASS__);
    }

    /**
     * Required by ArrayAccess interface.
     *
     * @internal
     *
     * @param int|string $offset The offset to unset
     */
    public function offsetUnset($offset): void
    {
        throw new fProgrammerException('The %s class does not support modifying XML', __CLASS__);
    }

    /**
     * Returns a well-formed XML string from the current element.
     *
     * @return string The XML
     */
    public function toXML()
    {
        return $this->__dom->ownerDocument->saveXML($this->__dom->parentNode === $this->__dom->ownerDocument ? $this->__dom->parentNode : $this->__dom);
    }

    /**
     * Executes an XPath query on the current element, returning an array of matching elements.
     *
     * @param string $path       The XPath path to query
     * @param bool   $first_only If only the first match should be returned
     *
     * @return (null|self|string)[]|false|null|self|string An array of matching elements, or a string or fXML object if `$first_only` is `TRUE`
     *
     * @psalm-return array<int|string, null|self|string>|false|null|self|string
     */
    public function xpath($path, $first_only = false)
    {
        $result = $this->query($path);

        if ($first_only) {
            if (! $result->length) {
                return;
            }
            $result = [$result->item(0)];
        } else {
            if (! $result->length) {
                return [];
            }
        }

        $keys_to_remove = [];
        $output = [];

        foreach ($result as $element) {
            if ($element instanceof DOMElement) {
                $child = new self($element);
                $child->__custom_prefixes = $this->__custom_prefixes;
                if ($child->__dom->namespaceURI && $child->__dom->prefix == '') {
                    $child->addCustomPrefix('__', $child->__dom->namespaceURI);
                }
                $output[] = $child;
            } elseif ($element instanceof DOMCharacterData) {
                $output[] = $element->data;
            } elseif ($element instanceof DOMAttr) {
                $key = $element->name;
                if ($element->prefix) {
                    $key = $element->prefix.':'.$key;
                }

                // We will create an attrname and attrname[0] key for each
                // attribute and if more than one is found we remove the
                // key attrname. If only one is found we remove attrname[0].
                $key_1 = $key.'[1]';

                if (isset($output[$key_1])) {
                    $i = 1;
                    while (isset($output[$key.'['.$i.']'])) {
                        $i++;
                    }

                    // This removes the key without the array index if more than one was found
                    unset($output[$key], $keys_to_remove[$key_1]);

                    $key = $key.'['.$i.']';
                } else {
                    $output[$key_1] = $element->nodeValue;
                    $keys_to_remove[$key_1] = true;
                }

                $output[$key] = $element->nodeValue;
            }
        }

        foreach ($keys_to_remove as $key => $trash) {
            unset($output[$key]);
        }

        if ($first_only) {
            return current($output);
        }

        return $output;
    }

    /**
     * Performs an XPath query on the current element, returning the raw results.
     *
     * @param string $path The XPath path to query
     *
     * @return DOMNodeList The matching elements
     *
     * @psalm-return DOMNodeList<DOMNode>
     */
    protected function query($path): DOMNodeList
    {
        if (! $this->__xpath) {
            $this->__xpath = new DOMXPath($this->__dom->ownerDocument);
            if ($this->__custom_prefixes) {
                foreach ($this->__custom_prefixes as $prefix => $namespace) {
                    $this->__xpath->registerNamespace($prefix, $namespace);
                }
            }
        }

        // Prevent spitting out errors to we can throw exceptions
        $old_setting = libxml_use_internal_errors(true);

        $result = $this->__xpath->query($path, $this->__dom);

        // We want it to be clear when XML parsing issues occur
        if ($result === false) {
            $errors = libxml_get_errors();
            $exception_message = '';

            foreach ($errors as $error) {
                $exception_message .= "\n".$error->message;
            }

            // If internal errors were off before, turn them back off
            if (! $old_setting) {
                libxml_use_internal_errors(false);
            }

            throw new fProgrammerException(str_replace('%', '%%', trim($exception_message)));
        }

        if (! $old_setting) {
            libxml_use_internal_errors(false);
        }

        return $result;
    }

    /**
     * Fixes HTML entities that aren't XML encoded and fixes ISO-8859-1/Windows-1252 encoded content that does not have an encoding attribute.
     *
     * @param string $xml The XML to fix
     *
     * @return string The fixed XML
     */
    private function fixEntitiesEncoding($xml)
    {
        preg_match('#^<\?xml.*? encoding="([^"]+)".*?\?>#i', $xml, $match);
        $encoding = empty($match[1]) ? null : $match[1];

        // Try to detect the encoding via the BOM
        if ($encoding === null) {
            if (substr($xml, 0, 3) == "\x0\x0\xFE\xFF") {
                $encoding = 'UTF-32BE';
            } elseif (substr($xml, 0, 3) == "\xFF\xFE\x0\x0") {
                $encoding = 'UTF-32LE';
            } elseif (substr($xml, 0, 2) == "\xFE\xFF") {
                $encoding = 'UTF-16BE';
            } elseif (substr($xml, 0, 2) == "\xFF\xFE") {
                $encoding = 'UTF-16LE';
            } else {
                $encoding = 'UTF-8';
            }
        }

        // This fixes broken encodings where the XML author puts ISO-8859-1 or
        // Windows-1252 into an XML file without an encoding or UTF-8 encoding
        if (preg_replace('#[^a-z0-9]#', '', strtolower($encoding)) == 'utf8') {
            // Remove the UTF-8 BOM if present
            $xml = preg_replace("#^\xEF\xBB\xBF#", '', $xml);
            fCore::startErrorCapture(E_NOTICE);
            $cleaned = iconv('UTF-8', 'UTF-8', $xml);
            if ($cleaned != $xml) {
                $xml = iconv('Windows-1252', 'UTF-8', $xml);
            }
            fCore::stopErrorCapture();
        }

        $num_matches = preg_match_all('#&(?!gt|lt|amp|quot|apos)\w+;#', $xml, $matches, PREG_SET_ORDER);
        if ($num_matches) {
            // We convert non-UTF-* content to UTF-8 because some character sets
            // don't have characters for all HTML entities
            if (substr(strtolower($encoding), 0, 3) != 'utf') {
                $xml = iconv($encoding, 'UTF-8', $xml);
                $xml = preg_replace('#^(<\?xml.*?) encoding="[^"]+"(.*?\?>)#', '\1 encoding="UTF-8"\2', $xml);
                $encoding = 'UTF-8';
            }

            $entities = [];
            foreach ($matches as $match) {
                $entities[$match[0]] = html_entity_decode($match[0], ENT_COMPAT, $encoding);
            }
            $xml = strtr($xml, $entities);
        }

        return $xml;
    }
}

/*
 * Copyright (c) 2007-2010 Will Bond <will@flourishlib.com>, others
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in
 * all copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 */
