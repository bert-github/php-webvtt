<?php

namespace W3C;


class WebVTTException extends \Exception {}


/** WebVTT represents a WebVTT file
 *
 *  A WebVTT object represents the parsed contents of a WebVTT file.
 *  It has methods to parse and write WebVTT text and some utility
 *  functions.
 *
 *  The same object can be used to parse multiple WebVTT files. Each
 *  call to the parse() or parse_file() methods clears the stored date
 *  (the cues, regions and styles properties) before storing the
 *  results of the new parse.
 *
 *  Example instantiation with WebVTT text:
 *
 *      $s = "WEBVTT\n\n00:00.000 --> 00:02.120\nHi!";
 *      $mycaptions = new \W3C\WebVTT($s);
 *
 *  Example instantiation with a file name:
 *
 *      $mycaptions = new W3C\WebVTT('captions.vtt');
 *
 *  This is equivalent to:
 *
 *      $s = file_get_contents('captions.vtt');
 *      $mycaptions = new \W3C\WebVTT($s);
 *
 *  Using parse() to parse WebVTT text:
 *
 *      $mycaptions = new \W3C\WebVTT();
 *      ...
 *      $mycaptions->parse("WEBVTT\n...");
 *      ...
 *      $mycaptions->parse("WEBVTT\n...");
 *
 *  Using parse_file() to parse WebVTT files:
 *
 *      $mycaptions = new \W3C\WebVTT();
 *      ...
 *      $mycaptions->parse_file('captions1.vtt');
 *      ...
 *      $mycaptions->parse_file('captions2.vtt');
 *
 *  The parser raises a WebVTTException exception when a parse error
 *  occurs. The object properties will then hold the results of
 *  parsing up to the error. The type of exception indicates what kind
 *  of error occurred. Example:
 *
 *      try {
 *        $myparser = new \W3C\WebVTT('captions.vtt');
 *        printf("%d cues found\n", count($myparser->cues));
 *      } catch (\W3C\WebVTTException $e) {
 *        printf("WebVTT error %d occurred, with message:\n%s\n",
 *          $e->getCode(), $e->getMessage());
 *      }
 *
 *  In a string context, the WebVTT object returns its data as a
 *  string in WebVTT syntax. You can also call the __toString() method
 *  explictly to get that string.
 *
 *  The write_file() method can write a WebVTT file:
 *
 *      $myparser->write_file('newcaptions.vtt');
 *
 *  It is equivalent to:
 *
 *      $s = myparser->__toString();
 *      file_put_contents('newcaptions.vtt', $s);
 */
class WebVTT implements \Stringable
{

  /** Error codes, passed to the WebVTTException.
   */
  public const E_IO = 1;
  public const E_WEBVTT = self::E_IO + 1;
  public const E_LINE = self::E_WEBVTT + 1;
  public const E_SETTING = self::E_LINE + 1;
  public const E_DUPLICATE = self::E_SETTING + 1;
  public const E_TIME = self::E_DUPLICATE + 1;
  public const E_CUESETTING = self::E_TIME + 1;

  /** cues represents the parsed WebVTT text as an array of cues
   *  Example:
   *
   *  [ [ "identifier" => "s2",
   *      "start" => "256.21",
   *      "end" => "259.01",
   *      "settings" => [ "align" => "right", "size" => "50%" ],
   *      "text" => "Is it an apple?\nOr an orange?" ],
   *    [ "identifier" => ''
   *      "start" => "259.1",
   *      "end" => "260.21",
   *      "settings" => [],
   *      "text" => "It is an orange." ] ]
   *
   *  It is an array where each entry is a cue, and each cue in turn is
   *  an array with the following entries:
   *
   *  identifier : the cue's ID, or null if none.
   *  start      : start time in seconds.
   *  end        : end time in seconds.
   *  settings   : an array of style and position properties.
   *  text       : text of the cue.
   *
   *  The text can contain style, language and voice tags (<i>,
   *  <font>, <lang>, <v>, etc.) and entities (&eacute;, etc.). Only
   *  the <v> tags have been removed and stored in the "voice" field.
   *  (But see the function as_html() to turn a cue text into an HTML
   *  fragment with the style tags replaced by HTML tags.)
   *
   *  If a cue has multiple lines, the lines are separated in the text
   *  field by line feed characters.
   */
  public array $cues = [];

  /** regions is an array with all regions defined in a WebVTT file
   *  Example:
   *
   *  [ [ "id" => "fred",
   *      "width" => "40%",
   *      "lines" => "3",
   *      "regionanchor" => "0%,100%",
   *      "viewportanchor" => "10%,90%",
   *      "scroll" => "up" ],
   *    [ "id" => "bill",
   *      "width" => "40%",
   *      "lines" => "3",
   *      "regionanchor" => "100%,100%",
   *      "viewportanchor" => "90%,90%",
   *      "scroll" => "up" ] ]
   */
  public array $regions = [];

  /** styles is string with the concatenation of all STYLE blocks
   *  Example: "::cue {background: yellow}\n::cue(b) {color: purple}"
   *
   *  The styles property ends with a newline (unless the property is
   *  empty). This is unlike the cue text, has a newline between
   *  lines, but not at the end.
   */
  public string $styles = '';


  /** Constructor
   *  \param $text optional WebVTT text or the name or URL of a WebVTT file
   *  \param $options parsing options (currently none are defined)
   *
   *  If the $text contains a line terminator, it is parsed as WebVTT
   *  text, otherwise it is assumed to be a file path or URL.
   8
   *  The constructor may raise a WebVTTException if a parse error
   *  occurs or if the passed file cannot be read.
   */
  public function __construct(string $text = null, array $options = null)
  {
    if (preg_match('/[\r\n]/', $text)) $this->parse($text);
    else $this->parse_file($text);
  }


  /** Replace the contents of this object with the results of parsing $text
   *  \param $text text of a WebVTT file
   *
   *  May raise a WebVTTException if parsing fails.
   */
  public function parse(string $text): void
  {
    $this->cues = [];
    $this->regions = [];
    $this->styles = '';
    $this->parse_internal($text, '<none>');
  }


  /** Parse a WebVTT file
   *  \param $file the path or URL to a WebVTT file
   *
   *  Raises a WebVTTException if parsing fails or if the file cannot
   *  be read.
   */
  public function parse_file($file)
  {
    $this->cues = [];
    $this->regions = [];
    $this->styles = '';
    $s = @file_get_contents($file);
    if ($s === false) $this->error(self::E_IO, '', -1, $file);
    $this->parse_internal($s, $file);
  }


  /** Return the contents of the WebVTT object as a string in WebVTT syntax
   *  \returns a string conforming to WebVTT syntax
   *
   *  The returned text is suitable for writing to a WebVTT file.
   *
   *  The returned text is not necessarily the same as the text that
   *  was originally parsed to create this WebVTT object. In
   *  particular, it will not have any text on the first line after
   *  "WEBVTT", it will lack any comment blocks ("NOTE"), all style
   *  blocks will be merged into a single block, timestamps will not
   *  contain hours if the hours are 0, and there may be less white
   *  space. The line terminators will be single line feeds.
   *
   *  However, the returned text contains all the data in the object
   *  and re-parsing the returned text will lead to the exact same
   *  object.
   */
  public function __toString(): string
  {
    $t = "WEBVTT\n\n";

    foreach ($this->regions as $r) {
      $t .= "REGION\n";
      if (isset($r['id'])) $t .= "id:$r[id]\n";
      if (isset($r['width'])) $t .= "width:$r[width]\n";
      if (isset($r['lines'])) $t .= "lines:$r[lines]\n";
      if (isset($r['regionanchor'])) $t .= "regionanchor:$r[regionanchor]\n";
      if (isset($r['viewportanchor'])) $t.="viewportanchor:$r[viewportanchor]\n";
      if (isset($r['scroll'])) $t .= "scroll:$r[scroll]\n";
      $t .= "\n";
    }

    if ($this->styles !== '')
      $t .= "STYLE\n$this->styles\n";

    foreach ($this->cues as $c) {
      if ($c['identifier'] !== '') $t .= "$c[identifier]\n";
      $t .= $this->secs_to_timestamp($c['start']);
      $t .= ' --> ';
      $t .= $this->secs_to_timestamp($c['end']);
      foreach ($c['settings'] as $k => $v) $t .= " $k:$v";
      $t .= "\n";
      // if ($c['text'] !== '') $t .= "$c[text]\n";
      $t .= $c['text'] . "\n\n";
    }

    return $t;
  }


  /** Write this object's data to a WebVTT file.
   *  \param $file the file name or URL to write to
   *
   *  See __toString() for what is written.
   *
   *  Raises a WebVTTException if the file cannot be written.
   */
  public function write_file(string $file): void
  {
    if (@file_put_contents($file, $this->__toString()) === false)
      $this->error(self::E_IO, '', -1, $file);
  }


  /** Convert a number of seconds to the form hh:mm:ss.hhh
   *  \param $seconds a number of seconds >= 0
   *  \returns a string of the form "hh:mm:ss.hhh" or "mm:ss.hhh"
   *
   *  The returned string will always have 2 digits for minutes, 2
   *  digits for seconds and 3 digits for thousands of seconds. The
   *  hours are omitted if 0.
   */
  public static function secs_to_timestamp(float $seconds): string
  {
    $minutes = floor($seconds / 60);
    $seconds -= 60 * $minutes;
    $hours = floor($minutes / 60);
    $minutes -= 60 * $hours;
    if ($hours == 0) return sprintf("%02d:%06.3f", $minutes, $seconds);
    else return sprintf("%d:%02d:%06.3f", $hours, $minutes, $seconds);
  }


  /** Parse WebVTT text
   *  \param $text text of a WebVTT file
   *  \param $file the name of the file being parsed, or "<none>"
   */
  protected function parse_internal(string $text, string $file): void
  {
    // Remove optional BOM.
    if (str_starts_with($text, "\u{FEFF}")) $text = substr($text, 1);

    $lines = preg_split('/\r\n|\r|\n/', $text);

    // First line must start with WEBVTT.
    if (!preg_match('/^WEBVTT[ \t]*$/', $lines[0]))
      $this->error(self::E_WEBVTT, $lines, 0, $file);

    // Second line must be empty.
    if ($lines[1] !== '')
      $this->error(self::E_LINE, $lines, 1, $file);

    $i = 2;

    // Region, style and comment blocks.
    while ($i <= array_key_last($lines))
      if ($lines[$i] === '')
        $i++;
      elseif (preg_match('/^REGION[ \t]*$/', $lines[$i]))
        $this->region_block($lines, $i, $file);
      elseif (preg_match('/^NOTE(?:[ \t]|$)/', $lines[$i]))
        $this->comment_block($lines, $i, $file);
      elseif (preg_match('/^STYLE[ \t]*$/', $lines[$i]))
        $this->style_block($lines, $i, $file);
      else
        break;

    // Cue and comment blocks.
    while ($i <= array_key_last($lines))
      if ($lines[$i] === '')
        $i++;
      elseif (preg_match('/^NOTE(?:[ \t]|$)/', $lines[$i]))
        $this->comment_block($lines, $i, $file);
      else
        $this->cue_block($lines, $i, $file);
  }

  /** Parse a "NOTE" block (comment block)
   *  \param $lines all the lines of the text being parsed
   *  \param $linenr the line number of the line to parse, by reference
   *  \param $file the name of the file being parsed, or "<none>"
   *
   *  A comment block starts with NOTE and ends before an empty line
   *  (or the end of file). The block is removed from the start of
   *  $text and discarded.
   *
   *  \todo: Check that the text of the block does not contain "-->".
   */
  private function comment_block(array $lines, int &$linenr, string $file): void
  {
    assert(str_starts_with($lines[$linenr], 'NOTE'));
    while (++$linenr <= array_key_last($lines) && $lines[$linenr] !== '');
  }


  /** Parse a "REGION" block
   *  \param $lines all the lines of the text being parsed
   *  \param $linenr the line number of the line to parse, by reference
   *  \param $file the name of the file being parsed, or "<none>"
   *
   *  A region block looks something like this:
   *
   *  REGION
   *  id:bill
   *  width:40%
   *  lines:3
   *  regionanchor:100%,100%
   *  viewportanchor:90%,90%
   *
   *  The method adds the region settings to the regions property.
   *
   *  \todo check the syntax of the values
   */
  private function region_block(array $lines, int &$linenr, string $file): void
  {
    assert(str_start_with($lines[$linenr], 'REGION'));
    $settings = [];
    while (++$linenr <= array_key_last($lines) && $lines[$linenr] !== '') {
      foreach (preg_split('/[ \t]+/', $lines[$linenr]) as $s) {
        if (!preg_match(
          '/^(id|width|lines|regionanchor|viewportanchor|scroll):(.+)$/',
          $s, $m))
          $this->error(self::E_SETTING, $s, $linenr, $file);
        if (isset($settings[$m[1]]))
          $this->error(self::E_DUPLICATE, $m[1], $linenr, $file);
        $settings[$m[1]] = $m[2];
      }
    }
    $this->regions[] = $settings;
  }


  /** Parse a style block
   *  \param $lines all the lines of the text being parsed
   *  \param $linenr the line number of the line to parse, by reference
   *  \param $file the name of the file being parsed, or "<none>"
   *
   *  A style block starts with "STYLE" and looks something like this:
   *
   *  STYLE
   *  ::cue {
   *    background: silver;
   *    color: red;
   *  }
   *
   *  The block ends at the next empty line. The method adds the text
   *  of the block (presumably CSS style rules) to the style property.
   *
   *  \todo Check that the text of the block does not contain "-->".
   */
  private function style_block(array $lines, int &$linenr, string $file): void
  {
    assert(str_starts_with($lines[$linenr], 'STYLE'));
    while (++$linenr <= array_key_last($lines) && $lines[$linenr] !== '')
      $this->styles .= "$lines[$linenr]\n";
  }


  /** Parse a cue block
   *  \param $lines all the lines of the text being parsed
   *  \param $linenr the line number of the line to parse, by reference
   *  \param $file the name of the file being parsed, or "<none>"
   *
   *  A cue block is something like this:
   *
   *  123
   *  00:00.000 --> 00:02.000
   *  That’s an, an, that’s an L!
   */
  private function cue_block(array $lines, int &$linenr, string $file): void
  {
    // Optional identifier, which is non-empty text not containing "-->".
    if (str_contains($lines[$linenr], '-->')) $cue['identifier'] = '';
    else $cue['identifier'] = $lines[$linenr++];

    // h:mm:ss.hhh --> h:mm:ss.hhh + optional settings.
    if (!preg_match(
      '/^(?:([0-9]+):)?([0-9]{2}):([0-9]{2}\.[0-9]{3})[ \t]+
      -->[ \t]+(?:([0-9]+):)?([0-9]{2}):([0-9]{2}\.[0-9]{3})
      (?:[ \t]+(.*))?$/x',
      $lines[$linenr], $m))
      $this->error(self::E_TIME, $lines[$linenr], $linenr, $file);
    $cue['start'] = floatval($m[3]) + 60*floatval($m[2]) + 3600*floatval($m[1]);
    $cue['end'] = floatval($m[6]) + 60*floatval($m[5]) + 3600*floatval($m[4]);
    if (isset($m[7]))
      foreach (preg_split('/[ \t]+/', $m[7] ?? '') as $s) {
        if (!preg_match('/^(vertical|line|position|size|align|region):(.*)$/',
          $s, $m))
          $this->error(self::E_CUESETTING, $s, $linenr, $file);
        $settings[$m[1]] = $m[2];
      }
    $cue['settings'] = $settings ?? [];

    // The cue text, ends before an empty line.
    $text = '';
    while (++$linenr <= array_key_last($lines) && $lines[$linenr] !== '') {
      if ($text !== '') $text .= "\n";
      $text .= $lines[$linenr];
    }
    $cue['text'] = new WebVTTCueText($text);

    // Add this cue to the cues property.
    $this->cues[] = $cue;
  }

  /** Throw an exception to signal a parsing error
   *  \param $code the type of error as an integer
   *  \param $context the remaining text to parse when error occurred
   *  \param $linenr the line number in the text where the error occurred
   *
   *  The exception will contain a numeric code (see the constants
   *  E_IO, E_LINE, etc.) and a message containing the name of the
   *  file being parsed (or "<none>"), the line number in the text
   *  being parsed, a description of the error and a part of the
   *  text where the error occurred. Example:
   *
   *     captions.vtt:2: error: Expected a line terminator: "foo23..."
   */
  protected function error(int $code, string $context, int $linenr,
    string $file): void
  {

    if (strlen($context) > 23) $context = substr_replace($context, '...', 20);
    $context = str_replace(["\r", "\n", "\t"], ['\r', '\n', '\t'], $context);
    if ($context !== '') $context = " at \"$context\"";
    switch ($code) {
      case self::E_IO:         $s = error_get_last()['message'];          break;
      case self::E_WEBVTT:     $s = 'Missing "WEBVTT" at start of text';  break;
      case self::E_LINE:       $s = 'Expected a line terminator';         break;
      case self::E_TIME:       $s = 'Expected a timestamp';               break;
      case self::E_SETTING:    $s = 'Unknown region setting';             break;
      case self::E_DUPLICATE:  $s = 'Region setting occurs twice';        break;
      case self::E_CUESETTING: $s = 'Unknown cue setting';                break;
    }
    $msg = sprintf("%s:%s: error: %s%s", $file, $linenr, $s, $context);
    throw new WebVTTException($msg, $code);
  }

}


/** Represents the text of a cue
 *
 *  The text of a cue consists of zero or more runs of plain text and
 *  tagged spans, which can be nested. E.g., this a cue with plain
 *  text (including a newline), a v-span, plain text (a space) and an
 *  i-span:
 *
 *      Where did he go?
 *      <v Esme>Hee!</v> <i>laughter</i>
 *
 *  Example usage:
 *
 *      $mycue = new WebVTTCueText("Hi there!\nHow do you do?");
 *      echo "Text is: ", $mycue, "\n";
 *      echo "In HTML: ", $mycue->as_html(), "\n";
 *
 *  A WebVTTCueText object is instantiated by passing it text like the
 *  above. It is a Stringable object, so it will be automatically
 *  converted to a string when used in a context where a string is
 *  expected. And it can also be converted to HTML text with the
 *  as_html() method.
 *
 *  Note 1: In WebVTT, cue text cannot contain empty lines. I.e., it
 *  cannot contain two newlines in a row. It also cannot contain the
 *  string "-->". The WebVTTCueText class, however, does not verify
 *  this. If initialized with such an invalid text, the resulting text
 *  when the object is converted back to a string will thus not be
 *  valid in WebVTT.
 *
 *  Note 2: Cue text can contain references to HTML character entities
 *  ("&eacute;", "&lt;", "&#39", etc.) but no "&" on its own. (Any "&"
 *  must be written as "&amp;".) The WebVTTCueText class does not
 *  verify that all ampersands are part of a character reference and
 *  also does not check that all such references are correct HTML.
 *
 *  Note 3: Tags can have classes, separated by periods, e.g.,
 *  "<v.special.loud>" has two classes, "special" and "loud". Classes
 *  cannot be empty in WebVTT: "<v..loud>" is an error. The
 *  WebVTTCueText class currently does not raise an error, but
 *  silently removes the empty class.
 *
 *  \todo Signal errors for invalid cue text.
 */
class WebVTTCueText implements \Stringable
{

  protected array $value = [];


  public function __construct(string $text)
  {
    // Split the text into lines and find the spans in each line.
    foreach (preg_split('/\r\n|\r|\n/', $text) as $line) {
      if ($this->value !== []) $this->value[] = "\n";
      $this->value = array_merge($this->value, $this->parse_line($line));
      if ($line !== '') throw new \Exception("Incorrect tag: $line");
    }
  }


  public function __toString(): string
  {
    return $this->flatten($this->value);
  }


  public function as_html(): string
  {
    // TODO
    return $this->value;
  }


  private function parse_line(string &$s): array
  {
    $spans = [];

    while ($s !== '') {
      if (preg_match('/^<(v|i|b|u|c|lang|ruby)(\.[^ \t>]+)?([ \t]+[^>]*)?>/',
          $s, $m)) {

        // Start tag.
        $tag = $m[1];
        $classes = ! isset($m[2]) ? [] : array_filter(explode('.', $m[2]));
        $annotation = $m[3] ?? '';
        $s = substr($s, strlen($m[0]));

        // Content, recursively.
        $content = $this->parse_line($s);

        // End tag.
        if ($tag === 'v' && $s === '') ; // </v> may be omitted at EOL
        elseif (str_starts_with($s,"</$tag>")) $s = substr($s,strlen("</$tag>"));
        else throw new \Exception("Missing </$tag>".($s ? " at \"$s\"" : ''));
        $spans[] = ['tag' => $tag, 'classes' => $classes,
          'annotation' => $annotation, 'text' => $content];

      } elseif (str_starts_with($s, '</')) {
        break;

      } elseif (str_starts_with($s, '<')) {
        throw new \Exception("Unknown tag: $s");

      } else {                  // Plain text span
        $n = strcspn($s, '<');
        $spans[] = substr($s, 0, $n);
        $s = substr($s, $n);
      }
    }
    return $spans;
  }


  private function flatten(array $spans): string
  {
    $s = '';
    foreach ($spans as $span)
      if (is_array($span)) {
        $s .= '<' . $span['tag'];
        if ($span['classes'] !== []) $s .= '.' . implode('.', $span['classes']);
        if ($span['annotation'] !== '') $s .= ' ' . $span['annotation'];
        $s .= '>';
        $s .= is_array($span['text']) ? $this->flatten($span['text']) : $span;
        $s .= '</' . $span['tag'] . '>';
      } else {
        $s .= $span;
      }
    return $s;
  }

}
