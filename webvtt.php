<?php

namespace W3C;

/** Error codes, passed to the WebVTTException.
 */
const E_IO = 1;
const E_WEBVTT = E_IO + 1;
const E_LINE = E_WEBVTT + 1;
const E_SETTING = E_LINE + 1;
const E_DUPLICATE = E_SETTING + 1;
const E_TIME = E_DUPLICATE + 1;
const E_CUESETTING = E_TIME + 1;
const E_UNKNOWN_TAG = E_CUESETTING + 1;
const E_UNCLOSED = E_UNKNOWN_TAG + 1;
const E_TAG = E_UNCLOSED + 1;
const E_SENTENCE = E_TAG + 1;


/** A WebVTTException is raised when a parsing error occurs.
 *
 */
class WebVTTException extends \Exception
{
  public function __construct(string $context = '', int $code = 0,
    string $file = '<none>', int $linenr = -1, ?\Throwable $previous = null)
  {
    if (strlen($context) > 23) $context = substr_replace($context, '...', 20);
    $context = str_replace(["\r", "\n", "\t"], ['\r', '\n', '\t'], $context);
    if ($context !== '') $context = " at \"$context\"";
    switch ($code) {
      case E_IO:          $s = error_get_last()['message'];             break;
      case E_WEBVTT:      $s = 'Missing "WEBVTT" at start of text';     break;
      case E_LINE:        $s = 'Expected a line terminator';            break;
      case E_TIME:        $s = 'Malformed or missing timestamp';        break;
      case E_SETTING:     $s = 'Unknown region setting';                break;
      case E_DUPLICATE:   $s = 'Region setting occurs twice';           break;
      case E_CUESETTING:  $s = 'Unknown cue setting';                   break;
      case E_UNKNOWN_TAG: $s = 'Unknown tag';                           break;
      case E_UNCLOSED:    $s = 'Missing close tag';                     break;
      case E_TAG:         $s = 'Incorrect tag';                         break;
      case E_SENTENCE:    $s = 'Sentence splitter made malformed text'; break;
    }
    parent::__construct(
      sprintf("%s:%s: error: %s%s", $file, $linenr + 1, $s, $context),
      $code, $previous);
  }
}


class WebVTTCueTextException extends \Exception {}


/** WebVTT represents the contents of a WebVTT file.
 *
 *  A WebVTT object represents the parsed contents of a WebVTT file.
 *  It has methods to parse and write WebVTT text and some utility
 *  functions.
 *
 *  A WebVTT object can be reused to parse multiple WebVTT files. Each
 *  call to the parse() or parse_file() methods clears the stored data
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
 *      // Create a WebVTT object.
 *      $mycaptions = new \W3C\WebVTT();
 *      ...
 *      // Parse some text, using the WebVTT object.
 *      $mycaptions->parse("WEBVTT\n...");
 *      ...
 *      // Parse some other text, reusing the WebVTT object.
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
 *  The parser raises a WebVTTException when a parse error occurs. The
 *  object properties will then hold the results of parsing up to the
 *  error. In addition to the message in English, a numeric code
 *  indicates what kind of error occurred. Example:
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

  /** cues represents the parsed WebVTT text as an array of cues
   *
   *  Example:
   *
   *      [ [ "identifier" => "s2",
   *          "start" => "256.21",
   *          "end" => "259.01",
   *          "settings" => [ "align" => "right", "size" => "50%" ],
   *          "text" => "Is it an apple?\nOr an orange?" ],
   *        [ "identifier" => ''
   *          "start" => "259.1",
   *          "end" => "260.21",
   *          "settings" => [],
   *          "text" => "It is an orange." ] ]
   *
   *  cues is an array where each entry is a cue, and each cue in turn is
   *  an array with the following entries:
   *
   *      identifier : the cue's ID, or '' if none.
   *      start      : start time in seconds.
   *      end        : end time in seconds.
   *      settings   : an array of style and position properties.
   *      text       : text of the cue, as a WebVTTCueText object.
   *
   *  The text field is shown as a string above, but is actually a
   *  WebVTTCueText object, which can be serialized to a string. It
   *  represents one or more lines of text and may contain plain text
   *  as well as spans of text enclosed in tags (<i>, <font>, <lang>,
   *  <v>, etc.) and HTML entities (&eacute;, etc.). Tagged spans can
   *  be nested, e.g.: "<v Joe>Hello <i>dear</i></v>"
   *
   *  The function as_html() can turn a cue text into an HTML fragment
   *  with the WebVTT tags replaced by HTML tags.
   */
  public array $cues = [];


  /** regions is an array with all regions defined in a WebVTT file.
   *
   *  E.g., the following two regions in WebVTT:
   *
   *      REGION
   *      id:fred
   *      width:40%
   *      lines:3
   *      regionanchor:0%,100%
   *      viewportanchor:10%,90%
   *      scroll:up
   *
   *      REGION
   *      id:bill
   *      width:40%
   *      lines:3
   *      regionanchor:100%,100%
   *      viewportanchor:90%,90%
   *      scroll:up
   *
   *  would be represented as:
   *
   *      [ [ "id" => "fred",
   *          "width" => "40%",
   *          "lines" => "3",
   *          "regionanchor" => "0%,100%",
   *          "viewportanchor" => "10%,90%",
   *          "scroll" => "up" ],
   *        [ "id" => "bill",
   *          "width" => "40%",
   *          "lines" => "3",
   *          "regionanchor" => "100%,100%",
   *          "viewportanchor" => "90%,90%",
   *          "scroll" => "up" ] ]
   */
  public array $regions = [];


  /** styles is string with the concatenation of all STYLE blocks.
   *
   *  E.g., the style blocks
   *
   *      STYLE
   *      ::cue {
   *        background: yellow}
   *
   *      STYLE
   *      ::cue(b) {color: purple}
   *
   *  would give this value for the styles property:
   *
   *      "::cue\n  {background: yellow}\n::cue(b) {color: purple}\n"
   *
   *  Note that the styles property ends with a newline (unless it is
   *  empty). This is unlike the cue text, which has a newline between
   *  lines, but not at the end.
   */
  public string $styles = '';


  /** Constructor.
   *  \param $text optional WebVTT text or the name or URL of a WebVTT file
   *  \param $options parsing options (currently none are defined)
   *
   *  If the $text contains a newline, it is parsed as WebVTT text,
   *  otherwise it is assumed to be the name of a file path or a URL.
   *
   *  The constructor may raise a WebVTTException if a parse error
   *  occurs or if the file cannot be read.
   */
  public function __construct(string $text = "WEBVTT\n\n", array $options = null)
  {
    if (preg_match('/[\r\n]/', $text)) $this->parse($text);
    else $this->parse_file($text);
  }


  /** Replace the contents of this object with the results of parsing $text.
   *  \param $text text of a WebVTT file
   *
   *  May raise a WebVTTException if parsing fails.
   *
   *  Note that, unlike the constructor, the argument is assumed to be
   *  text, not a file name. To read WebVTT text from a file, use the
   *  parse_file() method.
   */
  public function parse(string $text): void
  {
    $this->cues = [];
    $this->regions = [];
    $this->styles = '';
    $this->parse_internal($text, '<none>');
  }


  /** Parse a WebVTT file.
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
    if ($s === false) throw new WebVTTException('', E_IO, $file);
    // $this->error(E_IO, '', -1, $file);
    $this->parse_internal($s, $file);
  }


  /** Return the contents of the WebVTT object as a string in WebVTT syntax.
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
      throw new WebVTTException('', E_IO, $file);
      // $this->error(E_IO, '', -1, $file);
  }


  /** Create a transcript in HTML.
   *
   *  The method creates a text in HTML that contains all the cue
   *  text. By default, the text will be between "<div>" and </div>"
   *  tags, but the tag can be chnaged with the $sectiontag parameter.
   *
   *  If an array of time codes is passed in, the text will have
   *  multiple "<div>" elements: The cues are separated in groups at
   *  each time code. Empty groups are omitted. The time codes should
   *  be in increasing order.
   *
   *  The method will apply a heuristic to find the start and end of
   *  sentences and wrap each sentence in a "<p>" element (or another
   *  tag, if a $sentencetag parameter is provided).
   *
   *  A callback function can be given to replace the built-in
   *  heuristic. Its signature is
   *
   *      function (string $cuetext): string
   *
   *  The callback is given the collected text of all cues as a single
   *  string, which includes newlines ("\n") and tags ("<i>", "</b>",
   *  etc.). It should return a modified string with a form feed
   *  (\u{000C}) character inserted between sentences. It should also
   *  make other modifications as necessary around sentences, such as
   *  removing redundant white space.
   *
   *  E.g., if the cue text is
   *
   *      "<i>Hi there! What is your name?</i>"
   *
   *  the returned text could be
   *
   *      "<i>Hi there!\u{000C}What is your name?</i>"
   *
   *  with a form feed after the first sentence and the space before
   *  the second sentence removed.
   */
  public function as_html(?array $timecodes = [], string $sectiontag = 'div',
    string $sentencetag = 'p', ?callable $sentence_splitter = null)
  {
    if (is_null($sentence_splitter))
      $sentence_splitter = self::find_sentences(...);

    $alltext = '';
    $i = 0;
    $text = '';
    foreach ($this->cues as $cue) {
      if ($i < count($timecodes) && $cue['start'] >= $timecodes[$i]) {
        $alltext .= $this->to_section($text, $sectiontag, $sentencetag,
          $sentence_splitter);
        $text = '';
        $i++;
      }
      if ($text !== '') $text .= "\n";
      $text .= $cue['text'];
    }
    $alltext .= $this->to_section($text, $sectiontag, $sentencetag,
      $sentence_splitter);

    return $alltext;
  }


  /** Convert cue text to HTML, wrap each sentence in <$sentencetag>
   *  and the whole text in <$sectiontag>.
   *  \param $text cue text in WebVTT format
   *  \param $sectiontag the name of an HTML tag
   *  \param $sentencetag the name of an HTML tag
   *  \returns an HTML fragment
   */
  private static function to_section(string $text, string $sectiontag,
    string $sentencetag, callable $splitter): string
  {
    if ($text === '') return '';

    $text = $splitter($text);
    $text = self::fix_up_tags($text); // Close & reopen tags at sentence ends
    try {
      $text = new WebVTTCueText($text);
    } catch (WebVTTCueTextException $e) {
      throw new WebVTTException($text, E_SENTENCE, '<none>', -1, $e);
    }
    $text = "<$sentencetag>" .
    str_replace("\u{000C}", "</$sentencetag>\n<$sentencetag>",
      $text->as_html()) . "</$sentencetag>";
    return "<$sectiontag>\n$text\n</$sectiontag>\n";
  }


  /** Before each form feed, close all open tags and reopen them after it.
   *  \param $text cue text with tags and FF characters (\u{000C})
   *  \returns the same text with tags closed before the FF reopened after
   */
  private static function fix_up_tags($text): string
  {
    $opentags = [];
    $r = '';
    while ($text !== '') {
      if (str_starts_with($text, "\u{000C}")) {
        for ($i = count($opentags) - 1; $i >= 0; $i--)
          $r .= '</' . $opentags[$i][0] . '>';
        $r .= "\u{000C}";
        for ($i = 0; $i < count($opentags); $i++)
          $r .= '<' . $opentags[$i][0] . $opentags[$i][1] . '>';
        $text = substr($text, 1);
      } elseif (! str_starts_with($text, '<')) {
        $n = strcspn($text, "<\u{000C}");
        $r .= substr($text, 0, $n);
        $text = substr($text, $n);
      } elseif (preg_match('/^<(\/)?([^. >]+)([^>]*)>/', $text, $m)) {
        if ($m[1]) array_pop($opentags);   // pop
        else $opentags[] = [$m[2], $m[3]]; // push
        $r .= $m[0];
        $text = substr($text, strlen($m[0]));
      } else {
        throw new \Exception("Cannot happen!");
      }
    }
    return $r;
  }


  /** Heuristic function to insert form feeds between sentences.
   *  \param $text cue text
   *  \returns the same text with FF characters between sentences.
   */
  private static function find_sentences(string $text): string
  {
    // Full stop, question mark, exclamation mark or ellipis, followed
    // by space and an uppercase letter.
    $text = preg_replace(
      "/([.!?…](?:<[^>]*>)*)[ \n]+((?:<[^>]*>)*[\"'\\p{Pi}]?\\p{Lu})/u",
      "\$1\u{000C}\$2", $text);

    // Ideographic full stop, full-width exclamation mark, full-width
    //  question mark, followed by optional final or closing punctuation.
    $text = preg_replace("/([！？。｡][\\p{Pf}\\p{Pe}]?)[ \n]*([^< \n])/u",
      "\$1\u{000C}\$2", $text);

    return $text;
  }


  /** Convert a number of seconds to the form hh:mm:ss.hhh.
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


  /** Parse WebVTT text.
   *  \param $text text of a WebVTT file
   *  \param $file the name of the file being parsed, or "<none>"
   */
  protected function parse_internal(string $text, string $file): void
  {
    // Remove optional BOM (which is 3 bytes in UTF-8, the encoding we assume).
    if (str_starts_with($text, "\u{FEFF}")) $text = substr($text, 3);

    // Split the text into lines. Line endings can be CRLF, LF or CR.
    $lines = preg_split('/\r\n|\r|\n/', $text);

    // First line must be WEBVTT, optionally followed by space and more text.
    if (!preg_match('/^WEBVTT[ \t]*$/', $lines[0]))
      throw new WebVTTException($lines[0], E_WEBVTT, $file, 0);
      // $this->error(E_WEBVTT, $lines[0], 0, $file);

    // Second line must be empty.
    if ($lines[1] !== '')
      throw new WebVTTException($lines[1], E_LINE, $file, 1);
      // $this->error(E_LINE, $lines[1], 1, $file);

    // Region, style and comment blocks.
    $i = 2;
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


  /** Parse a "NOTE" block (comment block).
   *  \param $lines all the lines of the text being parsed
   *  \param $linenr the line number of the line to parse, by reference
   *  \param $file the name of the file being parsed, or "<none>"
   *
   *  A comment block starts with NOTE and ends before an empty line
   *  (or the end of file). The block is discarded.
   *
   *  \todo: Check that the text of the block does not contain "-->".
   */
  private function comment_block(array $lines, int &$linenr, string $file): void
  {
    assert(str_starts_with($lines[$linenr], 'NOTE'));
    while (++$linenr <= array_key_last($lines) && $lines[$linenr] !== '');
  }


  /** Parse a "REGION" block.
   *  \param $lines all the lines of the text being parsed
   *  \param $linenr the line number of the line to parse, by reference
   *  \param $file the name of the file being parsed, or "<none>"
   *
   *  A region block looks something like this:
   *
   *      REGION
   *      id:bill
   *      width:40%
   *      lines:3
   *      regionanchor:100%,100%
   *      viewportanchor:90%,90%
   *
   *  The method adds the region settings to the regions property.
   *
   *  \todo check the syntax of the values.
   */
  private function region_block(array $lines, int &$linenr, string $file): void
  {
    assert(str_start_with($lines[$linenr], 'REGION'));

    // Process each line in turn, stopping at an empty line. Each line
    // is split at spaces/tabs and each fragment should then be of the
    // form "key:value". Raise an error if a fragment is malformed or
    // the key is unknown, or the same key occurs twice.
    $settings = [];
    while (++$linenr <= array_key_last($lines) && $lines[$linenr] !== '') {
      foreach (preg_split('/[ \t]+/', $lines[$linenr]) as $s) {
        if (!preg_match(
          '/^(id|width|lines|regionanchor|viewportanchor|scroll):(.+)$/',
          $s, $m))
          throw new WebVTTException($s, E_SETTING, $file, $linenr);
          // $this->error(E_SETTING, $s, $linenr, $file);
        if (isset($settings[$m[1]]))
          throw new WebVTTException($m[1], E_DUPLICATE, $file, $linenr);
          // $this->error(E_DUPLICATE, $m[1], $linenr, $file);
        $settings[$m[1]] = $m[2];
      }
    }

    // Append this region to the regions property.
    $this->regions[] = $settings;
  }


  /** Parse a style block.
   *  \param $lines all the lines of the text being parsed
   *  \param $linenr the line number of the line to parse, by reference
   *  \param $file the name of the file being parsed, or "<none>"
   *
   *  A style block starts with "STYLE" and looks something like this:
   *
   *      STYLE
   *      ::cue {
   *        background: silver;
   *        color: red;
   *      }
   *
   *  The block ends at an empty line. The method adds the text of the
   *  block (presumably style rules in CSS syntax) to the style
   *  property.
   *
   *  \todo Check that the text of the block does not contain "-->".
   */
  private function style_block(array $lines, int &$linenr, string $file): void
  {
    assert(str_starts_with($lines[$linenr], 'STYLE'));
    while (++$linenr <= array_key_last($lines) && $lines[$linenr] !== '')
      $this->styles .= "$lines[$linenr]\n";
  }


  /** Parse a cue block.
   *  \param $lines all the lines of the text being parsed
   *  \param $linenr the line number of the line to parse, by reference
   *  \param $file the name of the file being parsed, or "<none>"
   *
   *  A cue block contains an optional identifier, timestamps and
   *  optional style settings, and lines of text, e.g.:
   *
   *      123
   *      00:00.000 --> 00:02.000
   *      That’s an, an, that’s an L!
   */
  private function cue_block(array $lines, int &$linenr, string $file): void
  {
    // Optional identifier, which is non-empty text not containing "-->".
    if (str_contains($lines[$linenr], '-->')) $cue['identifier'] = '';
    else $cue['identifier'] = $lines[$linenr++];

    // Timing (h:mm:ss.hhh --> h:mm:ss.hhh) and optional cue settings.
    if (!preg_match(
      '/^(?:([0-9]+):)?([0-9]{2}):([0-9]{2}\.[0-9]{3})[ \t]+
      -->[ \t]+(?:([0-9]+):)?([0-9]{2}):([0-9]{2}\.[0-9]{3})
      (?:[ \t]+(.*))?$/x',
      $lines[$linenr], $m))
      throw new WebVTTException($lines[$linenr], E_TIME, $file, $linenr);
      // $this->error(E_TIME, $lines[$linenr], $linenr, $file);
    $cue['start'] = floatval($m[3]) + 60*(floatval($m[2]) + 60*floatval($m[1]));
    $cue['end'] = floatval($m[6]) + 60*(floatval($m[5]) + 60*floatval($m[4]));
    if (isset($m[7]))           // There are cue setting after the time
      foreach (preg_split('/[ \t]+/', $m[7] ?? '') as $s) {
        if (!preg_match('/^(vertical|line|position|size|align|region):(.*)$/',
          $s, $m))
          throw new WebVTTException($s, E_CUESETTING, $file, $linenr);
          // $this->error(E_CUESETTING, $s, $linenr, $file);
        $settings[$m[1]] = $m[2];
      }
    $cue['settings'] = $settings ?? [];

    // The cue text, which ends before an empty line.
    $text = [];
    while (++$linenr <= array_key_last($lines) && $lines[$linenr] !== '')
      $text[] = $lines[$linenr];
    try {
      $cue['text'] = new WebVTTCueText($text);
    } catch (WebVTTCueTextException $e) {
      throw new WebVTTException($e->getMessage(),$e->getCode(),$file,$linenr-1);
    }

    // Append this cue to the cues property.
    $this->cues[] = $cue;
  }

}


/** Represents the text of a cue.
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
 *  also does not check that all such character entities exist in
 *  HTML.
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

  /** A tree structure containing the parsed spans of text.
   *
   *  The members of the array are either plain strings or arrays with
   *  four fields: tag, classes, annotation and text. E.g.:
   *
   *      [ 'Hello',
   *        [ 'tag' => 'i',
   *          'classes' => [],
   *          'annotation' => '',
   *          'text' => [ 'dear' ]
   *        ]
   *      ]
   *
   *  The tag field holds a string which can be "v", "b", "i", "u",
   *  "c", "ruby" or "lang". The classes property holds an array of
   *  strings. The annotation field holds a string. And the text field
   *  holds an array of the same type as the value property itself,
   *  i.e., its members are either strings or arrays with four fields
   *  as above.
   */
  protected array $value = [];


  /** Constructor.
   *  \param $lines a string or an array of strings
   *
   *  Parses the lines to find the spans of tagged text. Raises an
   *  exception if the tags are malformed, no properly nested, or have
   *  unknown names.
   */
  public function __construct(string|array $lines)
  {
    // If we're passed a single string, split it into lines.
    if (is_string($lines))
      $lines = preg_split('/\r\n|\r|\n/', $lines);

    // Find the spans in each line. If the parse_line() method does
    // not consume the whole line, it means there was a syntax error.
    foreach ($lines as $line) {
      if ($this->value !== []) $this->value[] = "\n";
      $this->value = array_merge($this->value, $this->parse_line($line));
      if ($line !== '') throw new WebVTTCueTextException($line, E_TAG);
    }
  }


  public function __toString(): string
  {
    return $this->flatten($this->value);
  }


  public function as_html(): string
  {
    return $this->flatten_html($this->value);
  }


  /** Parse text up to the next unmatched closing tag "</...>" or the end.
   *  \param $s the text to parse, passed by reference, will be modified
   *  \returns an array of strings and records (arrays with four fields)
   *
   *  The method removes all parsed text from the argument $s.
   *
   *  The members of the returned array can be strings (for spans of
   *  plain text) or arrays with four fields (for spans of tagged
   *  text). Tagged spans can be nested; see the value property above.
   */
  private function parse_line(string &$s): array
  {
    $spans = [];

    while ($s !== '' && ! str_starts_with($s, '</')) {
      if (preg_match(
          '/^<(v|i|b|u|c|lang|ruby|rt)(?:\.([^ \t>]+))?(?:[ \t]([^>]*))?>/',
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
        elseif (str_starts_with($s,"</$tag>")) $s=substr($s,strlen("</$tag>"));
        else throw new WebVTTCueTextException($s, E_UNCLOSED);
        $spans[] = ['tag' => $tag, 'classes' => $classes,
          'annotation' => $annotation, 'text' => $content];

      } elseif (str_starts_with($s, '<')) {
        throw new WebVTTCueTextException($s, E_UNKNOWN_TAG);

      } else {                  // Plain text span
        $n = strcspn($s, '<');
        $spans[] = substr($s, 0, $n);
        $s = substr($s, $n);
      }
    }
    return $spans;
  }


  /** Turn parsed text back into WebVTT syntax.
   *  \param an array with spans of text
   *  \return the array serialized to a string
   */
  private function flatten(array $spans): string
  {
    $s = '';
    foreach ($spans as $span)
      if (is_array($span)) {
        $s .= '<' . $span['tag'];
        if ($span['classes'] !== []) $s .= '.' . implode('.', $span['classes']);
        if ($span['annotation'] !== '') $s .= ' ' . $span['annotation'];
        $s .= '>';
        if (is_string($span['text'])) $s .= $span['text'];
        else $s .= $this->flatten($span['text']);
        $s .= '</' . $span['tag'] . '>';
      } else {
        $s .= $span;
      }
    return $s;
  }


  /** Map WebVTT tags to HTML tags.
   */
  private const htmltag = [ 'b' => 'b', 'i' => 'i', 'u' => 'u',
    'ruby' => 'ruby', 'rt' => 'rt', 'v' => 'span', 'lang' => 'span' ];


  /** Serialize the cue text as an HTML fragment.
   *  \param an array with spans of text
   *  \return the array serialized to a string
   */
  private function flatten_html(array $spans): string
  {
    $s = '';
    foreach ($spans as $span)
      if (is_string($span)) {
        $s .= $span;
      } else {
        $s .= '<' . self::htmltag[$span['tag']];
        if ($span['classes'] !== [])
          $s .= ' class="' .
            str_replace('"', '&quot;', implode(' ', $span['classes'])) . '"';
        if ($span['tag'] === 'lang')
          $s .= ' lang="' . str_replace('"','&quot;',$span['annotation']) . '"';
        elseif ($span['tag'] === 'v')
          $s .= ' title="' . str_replace('"','&quot;',$span['annotation']) . '"';
        $s .= '>';
        if (is_string($span['text']))
          $s .= $span['text'];
        else
          $s .= $this->flatten_html($span['text']);
        $s .= '</' . self::htmltag[$span['tag']] . '>';
      }
    return $s;
  }

}
