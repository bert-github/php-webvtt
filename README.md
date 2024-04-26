# php-webvtt
The WebVTT class contains a parser for WebVTT written in PHP

A WebVTT object represents the parsed contents of a WebVTT file. It
has methods to parse and write WebVTT text and to convert WebVTT text
to HTML.

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
object properties will then hold the results of parsing up to the
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

In a string context, the WebVTT object returns its data as a string in
WebVTT syntax. You can also call the `__toString()` method
explictly to get that string.

The `write_file()` method can write a WebVTT file:

``` php
$mycaptions->write_file('newcaptions.vtt');
```

It is equivalent to:

``` php
$s = myparser->__toString();
file_put_contents('newcaptions.vtt', $s);
```

## Converting back to WebVTT or to HTML

The function `as_html()` can turn a WebVTT object into a transcript
in HTML: It takes all cue text, splits it up into sentences,
possibly into multiple sections (`<div>` elements) at given time
codes, and returns an HTML fragment.

The function `cue_as_html()` can turn the text of a single cue
into an HTML fragment, with WebVTT tags (`<v>`, `<i>`, etc.)
replaced by HTML ones.

## The contents of a WebVTT object

The results of parsing a WebVTT text are stored in three object properties:

`cues` (`$mycaptions->cues`) represents the parsed WebVTT text as an array of cues.
Example:

``` php
[ [ "identifier" => "s2",
    "start" => "256.21",
    "end" => "259.01",
    "settings" => [ "align" => "right", "size" => "50%" ],
    "text" => "Is it an apple?\nOr an orange?" ],
  [ "identifier" => ''
    "start" => "259.1",
    "end" => "260.21",
    "settings" => [],
    "text" => "It is an orange." ] ]
```

`cues` is an array where each entry is a cue, and each cue in turn is
an array with the following entries:

* `identifier`: the cue's ID, or '' if none.
* `start`: start time in seconds.
* `end`: end time in seconds.
* `settings`: an array of style and position properties.
* `text`: text of the cue.

The text field represents one or more lines of text and may
contain plain text as well as spans of text enclosed in tags
(`<i>`, `<lang>`, `<v>`, etc.) and HTML entities (&eacute;,
etc.). Tagged spans can be nested, e.g.: `<v Joe>Hello
<i>dear</i></v>`.

`regions` (`$mycaptions->regions`)is an array with all regions defined in a WebVTT file.
E.g., the following two regions in WebVTT:

```
REGION
id:fred
width:40%
lines:3
regionanchor:0%,100%
viewportanchor:10%,90%
scroll:up

REGION
id:bill
width:40%
lines:3
regionanchor:100%,100%
viewportanchor:90%,90%
scroll:up

would be represented as:

``` php
[ [ "id" => "fred",
    "width" => "40%",
    "lines" => "3",
    "regionanchor" => "0%,100%",
    "viewportanchor" => "10%,90%",
    "scroll" => "up" ],
  [ "id" => "bill",
    "width" => "40%",
    "lines" => "3",
    "regionanchor" => "100%,100%",
    "viewportanchor" => "90%,90%",
    "scroll" => "up" ] ]
```

`styles` (`$mycaptions->styles`) is a string with the concatenation of all STYLE blocks.
E.g., the style blocks

```
STYLE
::cue {
  background: yellow}

STYLE
::cue(b) {color: purple}
```

would give this value for the styles property:

``` php
"::cue\n  {background: yellow}\n::cue(b) {color: purple}\n"
```

Note that the `styles` property ends with a newline (unless it is
empty). This is unlike the cue text, which has a newline between
lines, but not at the end.

