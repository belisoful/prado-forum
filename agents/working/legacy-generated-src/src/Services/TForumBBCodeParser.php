<?php

/**
 * TForumBBCodeParser class file.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @link https://github.com/belisoful/prado-forum
 * @license https://github.com/belisoful/prado-forum/blob/master/LICENSE
 * @since 1.0.0
 */

namespace Belisoful\Forum\Services;

use Belisoful\Forum\TForumManager;

/**
 * TForumBBCodeParser converts BBCode markup into sanitised HTML.
 *
 * Supported tags (all are nestable unless noted):
 *
 *   Text formatting:
 *     [b]bold[/b]  [i]italic[/i]  [u]underline[/u]  [s]strikethrough[/s]
 *     [sup]superscript[/sup]  [sub]subscript[/sub]
 *     [color=red]…[/color]  [size=14]…[/size]  [font=Arial]…[/font]
 *
 *   Structure:
 *     [center]…[/center]  [left]…[/left]  [right]…[/right]
 *     [hr]  (self-closing)
 *
 *   Links / media:
 *     [url]http://…[/url]  [url=http://…]label[/url]
 *     [img]http://…[/img]  [img width=400 height=300]http://…[/img]
 *     [email]a@b.com[/email]  [email=a@b.com]label[/email]
 *     [youtube]VIDEO_ID[/youtube]
 *     [video]http://…[/video]  (HTML5 <video>)
 *
 *   Lists:
 *     [list]  [*]item  [/list]
 *     [list=1]  [*]item  [/list]   (ordered)
 *
 *   Quoting:
 *     [quote]…[/quote]
 *     [quote=Username]…[/quote]
 *     [quote=Username;post=42]…[/quote]
 *
 *   Code:
 *     [code]…[/code]
 *     [code=php]…[/code]         (language hint for highlight.js)
 *     [icode]inline code[/icode]
 *
 *   Spoiler:
 *     [spoiler]…[/spoiler]
 *     [spoiler=Reveal text]…[/spoiler]
 *
 *   Table:
 *     [table][tr][th]Head[/th][td]Cell[/td][/tr][/table]
 *
 *   Misc:
 *     [noparse]…[/noparse]  (content passed through verbatim)
 *
 * Security: all user-supplied values are HTML-escaped before output.
 * URLs are validated against an allowlist of safe protocols.
 *
 * @author Brad Anderson <belisoful@icloud.com>
 * @since 1.0.0
 */
class TForumBBCodeParser
{
    /** @var TForumManager */
    private $_manager;

    /** @var string[] safe URL protocols */
    private static $_safeProtocols = ['http', 'https', 'ftp', 'ftps', 'mailto'];

    /** @var string[] CSS colour keywords (subset) */
    private static $_safeColors = [
        'red','blue','green','black','white','yellow','orange','purple',
        'pink','brown','gray','grey','cyan','magenta','lime','navy',
        'teal','silver','maroon','olive','aqua','coral','salmon','indigo',
    ];

    public function __construct(TForumManager $manager)
    {
        $this->_manager = $manager;
    }

    // ===================================================================
    // Public API
    // ===================================================================

    /**
     * Parse BBCode input and return safe HTML.
     *
     * @param string $input raw user content
     * @return string HTML string
     */
    public function parse(string $input): string
    {
        // Protect [noparse] blocks first.
        $noparse = [];
        $input   = $this->extractNoparse($input, $noparse);

        // Escape HTML entities in the remaining text so injections are neutralised.
        $input = htmlspecialchars($input, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        // Apply tag transformations in the right order.
        $input = $this->parseCode($input);
        $input = $this->parseFormatting($input);
        $input = $this->parseAlignment($input);
        $input = $this->parseColor($input);
        $input = $this->parseSize($input);
        $input = $this->parseFont($input);
        $input = $this->parseLinks($input);
        $input = $this->parseMedia($input);
        $input = $this->parseLists($input);
        $input = $this->parseQuote($input);
        $input = $this->parseSpoiler($input);
        $input = $this->parseTable($input);
        $input = $this->parseMisc($input);

        // Convert line-breaks to <br> (but not inside block elements).
        $input = $this->convertNewlines($input);

        // Restore [noparse] content (already HTML-escaped during extraction).
        $input = $this->restoreNoparse($input, $noparse);

        return $input;
    }

    // ===================================================================
    // Noparse extraction
    // ===================================================================

    private function extractNoparse(string $input, array &$placeholders): string
    {
        return preg_replace_callback(
            '/\[noparse\](.*?)\[\/noparse\]/si',
            function (array $m) use (&$placeholders): string {
                $key = '__NOPARSE_' . count($placeholders) . '__';
                $placeholders[$key] = htmlspecialchars($m[1], ENT_QUOTES, 'UTF-8');
                return $key;
            },
            $input
        );
    }

    private function restoreNoparse(string $input, array $placeholders): string
    {
        return str_replace(array_keys($placeholders), array_values($placeholders), $input);
    }

    // ===================================================================
    // Code blocks
    // ===================================================================

    private function parseCode(string $s): string
    {
        // [code=lang] or [code]
        $s = preg_replace_callback(
            '/\[code(?:=([a-zA-Z0-9+\-]+))?\](.*?)\[\/code\]/si',
            function (array $m): string {
                $lang  = $m[1] ? htmlspecialchars($m[1], ENT_QUOTES) : '';
                $class = $lang ? ' class="language-' . $lang . '"' : '';
                return '<pre><code' . $class . '>' . $m[2] . '</code></pre>';
            },
            $s
        );

        // [icode] inline
        $s = preg_replace('/\[icode\](.*?)\[\/icode\]/si', '<code>$1</code>', $s);

        return $s;
    }

    // ===================================================================
    // Text formatting
    // ===================================================================

    private function parseFormatting(string $s): string
    {
        $replacements = [
            '/\[b\](.*?)\[\/b\]/si'   => '<strong>$1</strong>',
            '/\[i\](.*?)\[\/i\]/si'   => '<em>$1</em>',
            '/\[u\](.*?)\[\/u\]/si'   => '<u>$1</u>',
            '/\[s\](.*?)\[\/s\]/si'   => '<del>$1</del>',
            '/\[sup\](.*?)\[\/sup\]/si' => '<sup>$1</sup>',
            '/\[sub\](.*?)\[\/sub\]/si' => '<sub>$1</sub>',
        ];

        foreach ($replacements as $pattern => $replacement) {
            $s = preg_replace($pattern, $replacement, $s);
        }

        return $s;
    }

    // ===================================================================
    // Alignment
    // ===================================================================

    private function parseAlignment(string $s): string
    {
        $s = preg_replace('/\[center\](.*?)\[\/center\]/si', '<div class="bbcode-center" style="text-align:center">$1</div>', $s);
        $s = preg_replace('/\[left\](.*?)\[\/left\]/si',     '<div class="bbcode-left"   style="text-align:left">$1</div>',   $s);
        $s = preg_replace('/\[right\](.*?)\[\/right\]/si',   '<div class="bbcode-right"  style="text-align:right">$1</div>',  $s);
        return $s;
    }

    // ===================================================================
    // Colour / size / font
    // ===================================================================

    private function parseColor(string $s): string
    {
        return preg_replace_callback(
            '/\[color=([^\]]+)\](.*?)\[\/color\]/si',
            function (array $m): string {
                $color = $this->sanitizeColor($m[1]);
                return '<span style="color:' . $color . '">' . $m[2] . '</span>';
            },
            $s
        );
    }

    private function sanitizeColor(string $color): string
    {
        $color = trim($color);
        // Hex colour
        if (preg_match('/^#[0-9a-fA-F]{3,6}$/', $color)) {
            return htmlspecialchars($color, ENT_QUOTES);
        }
        // Keyword colour
        if (in_array(strtolower($color), self::$_safeColors, true)) {
            return strtolower($color);
        }
        return 'inherit';
    }

    private function parseSize(string $s): string
    {
        return preg_replace_callback(
            '/\[size=(\d+)\](.*?)\[\/size\]/si',
            function (array $m): string {
                $size = min(max((int) $m[1], 6), 72); // clamp 6–72px
                return '<span style="font-size:' . $size . 'px">' . $m[2] . '</span>';
            },
            $s
        );
    }

    private function parseFont(string $s): string
    {
        return preg_replace_callback(
            '/\[font=([^\]]+)\](.*?)\[\/font\]/si',
            function (array $m): string {
                $font = htmlspecialchars(preg_replace('/[^a-zA-Z0-9 ,\-_]/', '', $m[1]), ENT_QUOTES);
                return '<span style="font-family:' . $font . '">' . $m[2] . '</span>';
            },
            $s
        );
    }

    // ===================================================================
    // Links
    // ===================================================================

    private function parseLinks(string $s): string
    {
        // [url=href]label[/url]
        $s = preg_replace_callback(
            '/\[url=([^\]]+)\](.*?)\[\/url\]/si',
            function (array $m): string {
                $href = $this->sanitizeUrl(html_entity_decode($m[1], ENT_QUOTES));
                return $href
                    ? '<a href="' . htmlspecialchars($href, ENT_QUOTES) . '" rel="nofollow noopener" target="_blank">' . $m[2] . '</a>'
                    : $m[2];
            },
            $s
        );

        // [url]href[/url]
        $s = preg_replace_callback(
            '/\[url\](.*?)\[\/url\]/si',
            function (array $m): string {
                $href = $this->sanitizeUrl(html_entity_decode($m[1], ENT_QUOTES));
                $safe = htmlspecialchars($href ?: $m[1], ENT_QUOTES);
                return $href
                    ? '<a href="' . $safe . '" rel="nofollow noopener" target="_blank">' . $safe . '</a>'
                    : $m[1];
            },
            $s
        );

        // [email=addr]label[/email]
        $s = preg_replace_callback(
            '/\[email=([^\]]+)\](.*?)\[\/email\]/si',
            function (array $m): string {
                $addr = htmlspecialchars(filter_var(html_entity_decode($m[1], ENT_QUOTES), FILTER_SANITIZE_EMAIL), ENT_QUOTES);
                return '<a href="mailto:' . $addr . '">' . $m[2] . '</a>';
            },
            $s
        );

        // [email]addr[/email]
        $s = preg_replace_callback(
            '/\[email\](.*?)\[\/email\]/si',
            function (array $m): string {
                $addr = htmlspecialchars(filter_var(html_entity_decode($m[1], ENT_QUOTES), FILTER_SANITIZE_EMAIL), ENT_QUOTES);
                return '<a href="mailto:' . $addr . '">' . $addr . '</a>';
            },
            $s
        );

        return $s;
    }

    private function sanitizeUrl(string $url): ?string
    {
        $url = trim($url);
        if ($url === '') {
            return null;
        }
        $parsed = parse_url($url);
        if (!$parsed) {
            return null;
        }
        $scheme = strtolower($parsed['scheme'] ?? 'https');
        if (!in_array($scheme, self::$_safeProtocols, true)) {
            return null;
        }
        return $url;
    }

    // ===================================================================
    // Media
    // ===================================================================

    private function parseMedia(string $s): string
    {
        // [img width=W height=H]url[/img]  or  [img]url[/img]
        $s = preg_replace_callback(
            '/\[img(?:\s+width=(\d+))?(?:\s+height=(\d+))?\](.*?)\[\/img\]/si',
            function (array $m): string {
                $url = $this->sanitizeUrl(html_entity_decode(trim($m[3]), ENT_QUOTES));
                if (!$url) {
                    return htmlspecialchars($m[3], ENT_QUOTES);
                }
                $w = $m[1] ? ' width="' . (int) $m[1] . '"' : '';
                $h = $m[2] ? ' height="' . (int) $m[2] . '"' : '';
                return '<img src="' . htmlspecialchars($url, ENT_QUOTES) . '"' . $w . $h . ' alt="" class="bbcode-img" loading="lazy">';
            },
            $s
        );

        // [youtube]VIDEO_ID[/youtube]
        $s = preg_replace_callback(
            '/\[youtube\]([a-zA-Z0-9_\-]{11})\[\/youtube\]/si',
            function (array $m): string {
                $id = htmlspecialchars($m[1], ENT_QUOTES);
                return '<div class="bbcode-video bbcode-youtube">'
                    . '<iframe width="560" height="315" src="https://www.youtube-nocookie.com/embed/' . $id . '" '
                    . 'frameborder="0" allowfullscreen loading="lazy"></iframe></div>';
            },
            $s
        );

        // [video]url[/video]  — HTML5 <video>
        $s = preg_replace_callback(
            '/\[video\](.*?)\[\/video\]/si',
            function (array $m): string {
                $url = $this->sanitizeUrl(html_entity_decode(trim($m[1]), ENT_QUOTES));
                if (!$url) {
                    return '';
                }
                return '<div class="bbcode-video">'
                    . '<video controls preload="metadata" style="max-width:100%">'
                    . '<source src="' . htmlspecialchars($url, ENT_QUOTES) . '">'
                    . '</video></div>';
            },
            $s
        );

        return $s;
    }

    // ===================================================================
    // Lists
    // ===================================================================

    private function parseLists(string $s): string
    {
        // Ordered: [list=1] or [list=a]
        $s = preg_replace_callback(
            '/\[list=([1aAiI])\](.*?)\[\/list\]/si',
            function (array $m): string {
                $type  = htmlspecialchars($m[1], ENT_QUOTES);
                $items = preg_replace('/\[\*\]\s*/s', '<li>', $m[2]);
                return '<ol type="' . $type . '">' . $items . '</ol>';
            },
            $s
        );

        // Unordered: [list]
        $s = preg_replace_callback(
            '/\[list\](.*?)\[\/list\]/si',
            function (array $m): string {
                $items = preg_replace('/\[\*\]\s*/s', '<li>', $m[1]);
                return '<ul>' . $items . '</ul>';
            },
            $s
        );

        return $s;
    }

    // ===================================================================
    // Quotes
    // ===================================================================

    private function parseQuote(string $s): string
    {
        // Iterative pass to handle nested quotes (innermost first via PCRE non-greedy).
        for ($i = 0; $i < 5; $i++) {
            $new = preg_replace_callback(
                '/\[quote(?:=([^\];]*)(?:;post=(\d+))?)?\]((?:(?!\[quote).)*?)\[\/quote\]/si',
                function (array $m): string {
                    $author = $m[1] ? htmlspecialchars(trim($m[1]), ENT_QUOTES) : '';
                    $postId = $m[2] ? (int) $m[2] : 0;
                    $header = '';
                    if ($author !== '') {
                        $header = '<cite class="bbcode-quote-author">' . $author . ' wrote:</cite>';
                        if ($postId > 0) {
                            $header = '<cite class="bbcode-quote-author">'
                                . '<a href="#post-' . $postId . '">' . $author . '</a> wrote:</cite>';
                        }
                    }
                    return '<blockquote class="bbcode-quote">' . $header . '<p>' . trim($m[3]) . '</p></blockquote>';
                },
                $s
            );

            if ($new === $s) {
                break;
            }
            $s = $new;
        }

        return $s;
    }

    // ===================================================================
    // Spoiler
    // ===================================================================

    private function parseSpoiler(string $s): string
    {
        return preg_replace_callback(
            '/\[spoiler(?:=([^\]]+))?\](.*?)\[\/spoiler\]/si',
            function (array $m): string {
                $label = $m[1] ? htmlspecialchars(trim($m[1]), ENT_QUOTES) : 'Show spoiler';
                return '<details class="bbcode-spoiler"><summary>' . $label . '</summary><div>' . $m[2] . '</div></details>';
            },
            $s
        );
    }

    // ===================================================================
    // Tables
    // ===================================================================

    private function parseTable(string $s): string
    {
        $s = preg_replace('/\[table\](.*?)\[\/table\]/si',  '<table class="bbcode-table">$1</table>', $s);
        $s = preg_replace('/\[tr\](.*?)\[\/tr\]/si',        '<tr>$1</tr>',  $s);
        $s = preg_replace('/\[th\](.*?)\[\/th\]/si',        '<th>$1</th>',  $s);
        $s = preg_replace('/\[td\](.*?)\[\/td\]/si',        '<td>$1</td>',  $s);
        return $s;
    }

    // ===================================================================
    // Misc
    // ===================================================================

    private function parseMisc(string $s): string
    {
        // [hr]  horizontal rule
        $s = str_ireplace('[hr]', '<hr>', $s);
        return $s;
    }

    // ===================================================================
    // Newline conversion
    // ===================================================================

    private function convertNewlines(string $s): string
    {
        // Don't add <br> inside block-level tags.
        $blockPattern = '<(?:pre|blockquote|ul|ol|li|table|tr|th|td|div|details|summary)[^>]*>';
        $parts        = preg_split('/(' . $blockPattern . '.*?<\/(?:pre|blockquote|ul|ol|li|table|tr|th|td|div|details|summary)>)/si', $s, -1, PREG_SPLIT_DELIM_CAPTURE);

        $result = '';
        foreach ($parts as $i => $part) {
            // Even indices are outside block elements → convert newlines.
            $result .= ($i % 2 === 0) ? nl2br($part) : $part;
        }

        return $result;
    }
}
