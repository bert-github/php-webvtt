# php-webvtt
The WebVTT class contains a parser for WebVTT written in PHP

A WebVTT object represents the parsed contents of a WebVTT file. It
has methods to parse and write WebVTT text and to convert WebVTT text
to HTML.

* `public function	__construct(string $text="WEBVTT\n\n", array $options=null)`
* `public function parse(string $text)`
* `public function parse_file(string $file)`
* `public function __toString(): string`
* `public function write_file(string $file)`
* `public function as_html(?array $timecodes=[], string $sectiontag='div', string $sentencetag='p', ?callable $sentence_splitter=null)`
* `public function cues(): array`
* `public function regions(): array`
* `public function notes(): array`
* `public function styles(): array`
* `public static function cue_as_html(string $cuetext, int $strictness=0): string`
* `public static function secs_to_timestamp(float $seconds): string`
* `public array $blocks = []`
* `public int $strictness = 0`

## Parsing WebVTT

A WebVTT object can be reused to parse multiple WebVTT files. Each
call to the `parse()` or `parse_file()` methods clears the
stored data (the cues, regions and styles properties) before storing
the results of the new parse.

Example instantiation with WebVTT text:

``` php
$s = "WEBVTT\n\n00:00.000 --> 00:02.120\nHi!";
$mycaptions = new \W3C\WebVTT($s);
```

Example instantiation with a file name:

``` php
$mycaptions = new W3C\WebVTT('captions.vtt');
```

This is equivalent to:

``` php
$s = file_get_contents('captions.vtt');
$mycaptions = new \W3C\WebVTT($s);
```

Using `parse()` to parse WebVTT text:

``` php
// Create a WebVTT object.
$mycaptions = new \W3C\WebVTT();
...
// Parse some text, using the WebVTT object.
$mycaptions->parse("WEBVTT\n...");
...
// Parse some other text, reusing the WebVTT object.
$mycaptions->parse("WEBVTT\n...");
```

Using `parse_file()` to parse WebVTT files:

``` php
$mycaptions = new \W3C\WebVTT();
...
$mycaptions->parse_file('captions1.vtt');
...
$mycaptions->parse_file('captions2.vtt');
```

The parser raises a WebVTTException when a parse error occurs. The
`blocks` property will then hold the results of parsing up to the
error. In addition to the message in English, a numeric code
indicates what kind of error occurred. Example:

``` php
try {
  $mycaptions = new \W3C\WebVTT('captions.vtt');
  printf("%d cues found\n", count($mycaptions->cues));
} catch (\W3C\WebVTTException $e) {
  printf("WebVTT error %d occurred, with message:\n%s\n",
    $e->getCode(), $e->getMessage());
}
```

There are currently two levels of strictness when parsing WebVTT
text: At the default level (0), the parser accepts arbitrary tags in
the cue text, arbitrary cue settings after a timestamp, and
arbitrary settings in a region block. At strictness level > 0, it
will instead check that those tags and settings are the ones
defined by the WebVTT specification. To use the stricter parsing,
create the WebVTT object with an extra option:

``` php
$mycaptions = new \W3C\WebVTT('captions.vtt', ['strictness' => 1]);
```

## Converting back to WebVTT or to HTML

In a string context, the WebVTT object returns its data as a string in
WebVTT syntax. You can also call the `__toString()` method
explictly to get that string.

The `write_file()` method can write a WebVTT file:

``` php
$mycaptions->write_file('newcaptions.vtt');
```

This is equivalent to:

``` php
$s = $mycaptions->__toString();
file_put_contents('newcaptions.vtt', $s);
```

The function `as_html()` can turn a WebVTT object into a transcript
in HTML: It takes all cue text, splits it up into sentences,
possibly into multiple sections (`<div>` elements) at given time
codes, and returns an HTML fragment.

The function `cue_as_html()` can turn the text of a single cue
into an HTML fragment, with WebVTT tags (`<v>`, `<i>`, etc.)
replaced by HTML ones.

## The contents of a WebVTT object

The results of parsing a WebVTT text are stored in the `blocks` property,
which is an array whose entries are objects of one of the classes
`WebVTTCue`, `WebVTTNote`, `WebVTTRegion` and `WebVTTStyle`.

Code that inspects the results of parsing could look like this:

``` php
foreach ($mycaptions->blocks as $b) {
  if ($b instanceof \W3C\WebVTTNote) {
    // do something with $b->text
  } elseif ($b instanceof \W3C\WebVTTStyle) {
    // do something with $b->text
  } elseif ($b instanceof \W3C\WebVTTRegion) {
    // do something with $b->settings
  } else {    // i.e., $b instanceof \W3C\WebVTTCue
    // do something with $b->identifier, $b->start,
    // $b->end, $b->settings and $b->text
  }
}
```

The method `styles()` returns an array of strings, corresponding
to the style rules of the STYLE blocks.

The method `regions()` returns an array of region settings,
corresponding to the settings of the REGION blocks.

The method `notes()` returns an array of strings, corresponding
to the texts of the NOTE blocks.

The method `cues()` returns an array of cues, e.g.:

``` php
$cues = $mycaptions->cues();
```

with result something like this:

```
[ [ "identifier" => "s2",
    "start" => 256.21,
    "end" => 259.01,
    "settings" => [ "align" => "right", "size" => "50%" ],
    "text" => "Is it an apple?\nOr an orange?" ],
  [ "identifier" => "",
    "start" => 260.21,
    "end" => 262.31,
    "settings" => [],
    "text" => "Neither." ] ]
```
