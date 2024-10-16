<?php

/*
* \todo When strictness > 0, also check that region settings have a
* value that corresponds to the keyword; ditto for cue settings; and
* check that notes and styles do not contain the string "-->".
*/

namespace W3C;

/** Version number.
 */
const VERSION = '0.1';

/** Error code, passed to the WebVTTException.
 *  Error while trying to read or write a file or remote resource.
 */
const E_IO = 1;

/** Error code, passed to the WebVTTException.
 *  Missing "WEBVTT" at start of file.
 */
const E_WEBVTT = E_IO + 1;

/** Error code, passed to the WebVTTException.
 *  Misssing empty line.
 */
const E_LINE = E_WEBVTT + 1;

/** Error code, passed to the WebVTTException.
 *  Unknown region setting.
 */
const E_SETTING = E_LINE + 1;

/** Error code, passed to the WebVTTException.
 *  Duplicate region setting.
 */
const E_DUPLICATE = E_SETTING + 1;

/** Error code, passed to the WebVTTException.
 *  Malformed or missing timestamp.
 */
const E_TIME = E_DUPLICATE + 1;

/** Error code, passed to the WebVTTException.
 *  Unknown cue setting.
 */
const E_CUESETTING = E_TIME + 1;

/** Error code, passed to the WebVTTException.
 *  Unknown tag.
 */
const E_UNKNOWN_TAG = E_CUESETTING + 1;

/** Error code, passed to the WebVTTException.
 *  Missing close tag.
 */
const E_UNCLOSED = E_UNKNOWN_TAG + 1;

/** Error code, passed to the WebVTTException.
 *  Incorrect tag.
 */
const E_TAG = E_UNCLOSED + 1;


/** A WebVTTException is raised when a parsing error occurs.
 *
 *  The method getMessage() gives an explanation in English. The
 *  method getCode() gives one of the code above (E_IO, E_LINE, etc.)
 */
class WebVTTException extends \Exception
{
  /** Create a WebVTTException.
   *  \param $context a relevant bit of text from the text being parsed
   *  \param $code indicated the kind of error (one of E_IO, E_TAB, etc)
   *  \param $file the name of the file being parsed, "<none>" by default
   *  \param $linenr the line number in that file, -1 by default
   *  \param $previous the exception that led to this one, null by default
   */
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
    }
    parent::__construct(
      sprintf("%s:%s: error: %s%s", $file, $linenr + 1, $s, $context),
      $code, $previous);
  }
}


/** Base class for the blocks of a WeVTT file.
 */
class WebVTTBlock {}


/** Represents a cue block.
 *
 *  A cue has the following properties:
 *
 *      identifier : the cue's ID, or '' if none.
 *      start      : start time in seconds.
 *      end        : end time in seconds.
 *      settings   : an array of style and position properties.
 *      text       : text of the cue.
 *
 *  Example:
 *
 *      "identifier" => "s2",
 *      "start" => 256.21,
 *      "end" => 259.01,
 *      "settings" => [ "align" => "right", "size" => "50%" ],
 *      "text" => "Is it an apple?\nOr an orange?"
 *
 *  The text field represents one or more lines of text and may
 *  contain plain text as well as spans of text enclosed in tags
 *  (<i>, <font>, <lang>, <v>, etc.) and HTML entities (&eacute;,
 *  etc.). Tagged spans can be nested, e.g.: "<v Joe>Hello
 *  <i>dear</i></v>".
 */
class WebVTTCue extends WebVTTBlock
{
  public string $identifier;    // the cue's ID, or '' if none
  public float $start;          // start time in seconds
  public float $end;            // end time in seconds
  public array $settings;       // style and position properties
  public string $text;          // text of the cue
  public function __construct(string $identifier, float $start, float $end,
    array $settings, string $text)
  {
    $this->identifier = $identifier;
    $this->start = $start;
    $this->end = $end;
    $this->settings = $settings;
    $this->text = $text;
  }
  public function __toString()
  {
    $t = '';
    if ($this->identifier) $t .= $this->identifier . "\n";
    $t .= WebVTT::secs_to_timestamp($this->start);
    $t .= ' --> ';
    $t .= WebVTT::secs_to_timestamp($this->end);
    if ($this->settings) foreach ($this-settings as $k => $v) $t .= "$k:$v";
    return $t .= "\n$this->text\n";
  }
}


/** Represents a region.
 *
 *  A WebVTTRegion has one property, $settings, which contains the
 *  parsed settings of a region.
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
 *      [ "id" => "fred",
 *        "width" => "40%",
 *        "lines" => "3",
 *        "regionanchor" => "0%,100%",
 *        "viewportanchor" => "10%,90%",
 *        "scroll" => "up" ]
 *  and
 *      [ "id" => "bill",
 *        "width" => "40%",
 *        "lines" => "3",
 *        "regionanchor" => "100%,100%",
 *        "viewportanchor" => "90%,90%",
 *        "scroll" => "up" ]
 */
class WebVTTRegion extends WebVTTBlock
{
  public array $settings;
  public function __construct(string $settings) {$this->settings = $settings;}
  public function __toString()
  {
    $s = '';
    foreach ($this->settings as $k => $v) $s .= "$k:$v\n";
    return "REGION\n$s";
  }
}


/** Represents a STYLE block.
 *
 *  The object has one proprty, $text, with the text of the style block.
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
 *  would give these values for the styles property:
 *
 *      "::cue\n  {background: yellow}\n"
 * and
 *      "::cue(b) {color: purple}\n"
 *
 *  Note that the text property ends with a newline (unless it is
 *  empty). This is unlike the cue text (see WebVTTCue), which has a
 *  newline between lines, but not at the end.
 */
class WebVTTStyle extends WebVTTBlock
{
  public string $text;
  public function __construct(string $text) {$this->text = $text;}
  public function __toString() {return "STYLE\n$this->text";}
}


/** Represents a comment (NOTE) block.
 *
 *  The text property contains thre text of the comment.
 */
class WebVTTNote extends WebVTTBlock
{
  public string $text;
  public function __construct(string $text) {$this->text = $text;}
  public function __toString() {return "NOTE\n$this->text";}
}


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
 *        printf("%d cues found\n", count($myparser->cues()));
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
 *
 *  The function as_html() can turn a WebVTT object into a transcript
 *  in HTML: It takes all cue text, splits it up into sentences,
 *  possibly into multiple sections (<div> elements) at given time
 *  codes, and returns an HTML fragment.
 *
 *  The function cue_as_html() can turn the text of a single cue into
 *  an HTML fragment, with WebVTT tags (<v>, <i>, etc.) replaced by
 *  HTML ones.
 */
class WebVTT implements \Stringable
{

  /** An array with all blocks (REGION, STYLE, NOTE, an cue).
   *  Each entry is an object of class WebVTTRegion, WebVTTStyle,
   *  WebVTTNote or WebVTTCue.
   */
  public array $blocks = [];


  /** Level of strictness of grammar checking.
   *  Currently only two levels are defined.
   *
   *  When $strictness <= 0, the parser accepts unknown tags, unknown
   *  cue settings and unknown region settings. When $trictness is >
   *  0, it will instead throw a WebVTTException.
   *
   *  Currently, even with $strictness > 0, not all restrictions of
   *  the WebVTT specification are checked. E.g., the parser currently
   *  doesn't check the syntax of the values of the region settings,
   *  and omits to check that a comment (NOTE block) does not contain
   *  the string "-->".
   */
  public int $strictness = 0;


  /** Constructor.
   *  \param $text optional WebVTT text or the name or URL of a WebVTT file
   *  \param $options parsing options
   *
   *  If no text is passed in, the object is intialized as if a WebVTT
   *  file without any cues was given.
   *
   *  If the $text contains a newline, it is parsed as WebVTT text,
   *  otherwise it is assumed to be a file path or a URL.
   *
   *  The constructor may raise a WebVTTException if a parse error
   *  occurs or if the file cannot be read.
   *
   *  Only one option is currently defined. If $options contains the
   *  field "strictness", it sets the $strictness property, which
   *  enables stricter grammar checks. See there for an explanation of
   *  the values. E.g.:
   *
   *      $myobject = new WebVTT("captions.vtt", ["strictness" => 1]);
   */
  public function __construct(string $text = "WEBVTT\n\n",
    array $options = null)
  {
    $this->strictness = $options['strictness'] ?? 0;

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
    $this->blocks = [];
    $this->parse_internal($text, '<none>');
  }


  /** Parse a WebVTT file.
   *  \param $file the path or URL to a WebVTT file
   *
   *  Raises a WebVTTException if parsing fails or if the file cannot
   *  be read.
   */
  public function parse_file(string $file)
  {
    $this->blocks = [];
    $s = @file_get_contents($file);
    if ($s === false) throw new WebVTTException('', E_IO, $file);
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
   *  "WEBVTT", timestamps will not contain hours if the hours are 0,
   *  and there may be less white space. The line terminators will be
   *  single line feeds.
   *
   *  However, the returned text contains all the data in the object
   *  and re-parsing the returned text will lead to the exact same
   *  object.
   */
  public function __toString(): string
  {
    return "WEBVTT\n\n" . implode("\n", $this->blocks);
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
  }


  /** Create a transcript in HTML.

   *  \param $timecodes an array of times in seconds to split the transcript
   *  \param $sectiontag the name of an HTML tag, default is "div"
   *  \param $sentencetag the name of an HTML tag, default is "p"
   *  \param $sentence_splitter a callback to override the sentence splitter
   *
   *  The method creates a text in HTML that contains all the cue
   *  text. By default, the text will be between "<div>" and </div>"
   *  tags, but the tag can be changed with the $sectiontag parameter.
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
    string $sentencetag = 'p', ?callable $sentence_splitter = null): string
  {
    if (is_null($sentence_splitter))
      $sentence_splitter = self::find_sentences(...);

    $alltext = '';
    $i = 0;
    $text = '';
    foreach ($this->cues() as $cue) {
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
      $sentence_splitter, $this->strictness);

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
    string $sentencetag, callable $splitter, int $strictness = 0): string
  {
    if ($text === '') return '';

    $text = $splitter($text);
    // Close & reopen tags at sentence ends:
    $text = self::fix_up_tags($text, $strictness);
    $text = "<$sentencetag>" .
      str_replace("\u{000C}", "</$sentencetag>\n<$sentencetag>",
        self::cue_as_html($text)) . "</$sentencetag>";
    return "<$sectiontag>\n$text\n</$sectiontag>\n";
  }


  /** Map WebVTT tags to HTML tags.
   */
  private const htmltag = [ 'b' => 'b', 'i' => 'i', 'u' => 'u',
    'ruby' => 'ruby', 'rt' => 'rt', 'v' => 'span', 'lang' => 'span' ];


  /** Convert a cue text in WebVTT syntax to one with HTML tags.
   *  \param $cuetext text of a cue including tags (<i>, <v>...) and newlines
   *  \returns the text with WebVTT tags replaced by HTML ones
   */
  public static function cue_as_html(string $cuetext, int $strictness = 0):
    string
  {
    return preg_replace_callback(
      '/<(\/)?([^. \t>]+)(?:\.([^ \t>]*))?(?:[ \t]([^>]*))?>/',
      function($m) use ($strictness) {
        if (! array_key_exists($m[2], self::htmltag))	{ // Unknown tag
          if ($strictness <= 0) return ''; // Just omit the tag
          throw new WebVTTException($m[0], E_UNKNOWN_TAG);
        }
        if ($m[1]) return '</' . self::htmltag[$m[2]] . '>'; // End tag
        $class = empty($m[3]) ? '' :
          ' class="' . str_replace(['.','"'],[' ','&quot;'],$m[3]) . '"';
        if ($m[2] === 'v')
          $annot = ' title="' . str_replace('"','&quot;',$m[4]??'') . '"';
        elseif ($m[2] === 'lang')
          $annot = ' lang="' . str_replace('"','&quot;',$m[4]??'') . '"';
        else
          $annot = '';
        return '<' . self::htmltag[$m[2]] . $class . $annot . '>';
      },
      $cuetext);
  }


  /** Before each form feed, close all open tags and reopen them after
   *  it; can also be used to verify that all tags are properly matched.
   *  \param $text cue text with tags and FF characters (\u{000C})
   *  \returns the same text with tags closed before the FF and reopened after
   *
   *  The method has two functions: It checks that a WebVTT cue text
   *  is correct, i.e., it checks that tags are properly paired; and
   *  it closes all open tags just before a form feed and reopens them
   *  after. This is used by as_html() when splitting a text into
   *  sentences.
   *
   *  \todo Also check that the tags are among the known ones (v, i,
   *  b, u, c, lang, ruby, rt and timestamp) and that only v and lang
   *  have an annotation.
   */
  private static function fix_up_tags($text, int $strictness = 0,
    string $file = null, int $linenr = -1): string
  {
    $opentags = [];
    $r = '';
    while ($text !== '') {
      if (str_starts_with($text, "\u{000C}")) { // Sentence end
        for ($i = count($opentags) - 1; $i >= 0; $i--)
          $r .= '</' . $opentags[$i][0] . '>';
        $r .= "\u{000C}";
        for ($i = 0; $i < count($opentags); $i++)
          $r .= '<' . $opentags[$i][0] . $opentags[$i][1] . '>';
        $text = substr($text, 1);
      } elseif (! str_starts_with($text, '<')) { // Text
        $n = strcspn($text, "<\u{000C}");
        $r .= substr($text, 0, $n);
        $text = substr($text, $n);
      } elseif (preg_match('/^<\/([^>]+)>/', $text, $m)) { // End tag
        $h = array_pop($opentags);
        if (! $h || $h[0] != $m[1]) throw new WebVTTException($m[0], E_TAG);
        $r .= $m[0];
        $text = substr($text, strlen($m[0]));
      } elseif (preg_match(     // Timestamp
          '/^<(?:[0-9]+:)?[0-9]{2}:[0-9]{2}\.[0-9]{3}>/', $text, $m)) {
        $r .= $m[0];
        $text = substr($text, strlen($m[0]));
      } elseif (preg_match('/^<([^. \t>]+)([^>]*)>/', $text, $m)) {
        if ($strictness > 0 &&
          !preg_match('/^(?:c|i|b|u|ruby|rt|v|lang)$/', $m[1]))
          throw new WebVTTException($m[0], E_UNKNOWN_TAG);
        $opentags[] = [$m[1], $m[2]]; // Push an open tag on the stack
        $r .= $m[0];
        $text = substr($text, strlen($m[0]));
      } else {
        throw new WebVTTException($text, E_TAG);
      }
    }
    if (count($opentags) == 1 && $opentags[0][0] == 'v') {
      $r .= '</v>';             // </v> at the end may be omitted
    } elseif (count($opentags) !== 0)
      throw new WebVTTException($opentags[0][0], E_UNCLOSED, $file, $linenr);
    return $r;
  }


  /** Heuristic function to insert form feeds between sentences.
   *  \param $text cue text
   *  \returns the same text with FF characters between sentences.
   *
   *  The method currently looks for punctuation that can end a
   *  sentence followed by space and an uppercase letter (with
   *  possibly some other quote marks in between); and also for
   *  ideographic full stops.
   */
  private static function find_sentences(string $text): string
  {
    // Full stop, question mark, exclamation mark or ellipis, followed
    // by space, optional tags (<...>), optional "(" or "[", and an
    // uppercase letter.
    $text = preg_replace(
      "/([.!?…](?:<[^>]*>)*)[ \t\n]+((?:<[^>]*>)*[-— \t\"'\\p{Pi}([]*\\p{Lu})/u",
      "\$1\u{000C}\$2", $text);

    // Ideographic full stop, full-width exclamation mark, full-width
    //  question mark, followed by optional final or closing punctuation.
    $text = preg_replace("/([！？。｡][\\p{Pf}\\p{Pe}]?)[ \t\n]*([^< \t\n])/u",
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

    // Second line must be empty.
    if ($lines[1] !== '')
      throw new WebVTTException($lines[1], E_LINE, $file, 1);

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
    $text = strlen($lines[$linenr]) > 4 ? substr($lines[$linenr], 5)."\n" : '';
    while (++$linenr <= array_key_last($lines) && $lines[$linenr] !== '')
      $text .= $lines[$linenr] . "\n";
    $this->blocks[] = new WebVTTNote($text);
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
        if (!preg_match('/^([^:]+):(.+)$/', $s, $m) ||
          ($this->strictness > 0 &&
            !preg_match(
              '/^(?:id|width|lines|regionanchor|viewportanchor|scroll)$/',
              $m[1])))
          throw new WebVTTException($s, E_SETTING, $file, $linenr);
        if (isset($settings[$m[1]]))
          throw new WebVTTException($m[1], E_DUPLICATE, $file, $linenr);
        $settings[$m[1]] = $m[2];
      }
    }

    // Append this region to the regions property.
    $this->blocks[] = new WebVTTRegion($settings);
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
    $style = '';
    while (++$linenr <= array_key_last($lines) && $lines[$linenr] !== '')
      $style .= "$lines[$linenr]\n";

    $this->blocks[] = new WebVTTStyle($style);
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
    if (str_contains($lines[$linenr], '-->')) $identifier = '';
    else $identifier = $lines[$linenr++];

    // Timing (h:mm:ss.hhh --> h:mm:ss.hhh) and optional cue settings.
    if (!preg_match(
      '/^(?:([0-9]+):)?([0-9]{2}):([0-9]{2}\.[0-9]{3})[ \t]+
      -->[ \t]+(?:([0-9]+):)?([0-9]{2}):([0-9]{2}\.[0-9]{3})
      (?:[ \t]+(.*))?$/x',
      $lines[$linenr], $m))
      throw new WebVTTException($lines[$linenr], E_TIME, $file, $linenr);
    $start = floatval($m[3]) + 60*(floatval($m[2]) + 60*floatval($m[1]));
    $end = floatval($m[6]) + 60*(floatval($m[5]) + 60*floatval($m[4]));
    $settings = [];
    if (isset($m[7]))           // There are cue setting after the time
      foreach (preg_split('/[ \t]+/', $m[7] ?? '') as $s) {
        if (!preg_match('/^([^:]+):(.*)$/', $s, $m) ||
          ($this->strictness > 0 &&
            !preg_match('/^(?:vertical|line|position|size|align|region)$/',
              $m[1])))
          throw new WebVTTException($s, E_CUESETTING, $file, $linenr);
        $settings[$m[1]] = $m[2];
      }

    // The cue text, which ends before an empty line.
    $text = '';
    while (++$linenr <= array_key_last($lines) && $lines[$linenr] !== '') {
      if ($text !== '') $text .= "\n";
      $text .= self::fix_up_tags($lines[$linenr], $this->strictness, $file,
        $linenr);
    }

    // Append this cue to the cues property.
    $this->blocks[] = new WebVTTCue($identifier, $start, $end,
      $settings, $text);
  }


  /** Returns the cue blocks of the object as an array, where each entry
   *  is an array with five fields. Each entry has fields "identifier",
   *  "start", "end", "settings" and "text", e.g.:
   *
   *      [ [ "identifier" => "s2",
   *          "start" => 256.21,
   *          "end" => 259.01,
   *          "settings" => [ "align" => "right", "size" => "50%" ],
   *          "text" => "Is it an apple?\nOr an orange?" ],
   *        [ "identifier" => "",
   *          "start" => 260.21,
   *          "end" => 262.31,
   *          "settings" => [],
   *          "text" => "Neither." ] ]
   */
  public function cues(): array
  {
    return array_map(
      fn($c) => ['identifier' => $c->identifier, 'start' => $c->start,
        'end' => $c->end, 'settings' => $c->settings, 'text' => $c->text],
      array_filter($this->blocks, fn($b) => $b instanceof WebVTTCue));
  }


  /** Returns the regions of the abject as an array, where each entry
   *  is an array of settings. E.g.:
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
  public function regions(): array
  {
    return array_map(
      fn($r) => $r->settings,
      array_filter($this->blocks, fn($b) => $b instanceof WebVTTRegion));
  }


  /** Returns the comment blocks of the object as an array of strings.
   */
  public function notes(): array
  {
    return array_map(
      fn($c) => $c->text,
      array_filter($this->blocks, fn($b) => $b instanceof WebVTTNote));
  }


  /** Returns the style blocks of the object as an array of strings.
   */
  public function styles(): string
  {
    return implode(
      "\n",
      array_filter($this->blocks, fn($b) => $b instanceof WebVTTStyle));
  }

}
