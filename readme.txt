=== Sparkplug ===
Contributors: beaulebens
Tags: sparkline, frequency, posts, widget, jquery
Requires at least: 2.7
Tested up to: 2.8.6
Stable tag: trunk
Donate link: http://dentedreality.com.au

Provides a widget (or template tag) to show a small sparkline chart with your the number of posts per day.

== Description ==
Sparkplug gives you a neat little [sparkline](http://en.wikipedia.org/wiki/Sparklines) chart that shows you how many posts you've had on your blog per day. On any "listing" page other than the homepage it will switch to showing you bars for posts in the current "section" and a line for all posts. This allows you to see how active the current section is in comparison to the blog as a whole.

Currently tested on the homepage, tags and category pages.

Comes packaged as a widget, or you can use the template tag to insert it directly into a theme file.

== Installation ==
Upload to your plugins directory then go to Plugins and activate it. You might also want to see the section on Usage to see anything happen.

== Usage ==
Sparkplug can be used either as a widget, directly within a theme file.

NOTE: You may only have ONE instance of Sparkplug appear on a page at a time. It will automatically detect if there has already been one and subsequent requests for it will be ignored.

= Widget =
1. Go to Appearance > Widgets and switch to the sidebar you want Sparkplug to appear in
2. Click the "Add" link on the "Sparkplug" widget.
3. Drag and Drop the widget to the position in your sidebar you want it to appear. It goes nicely at the top :)
4. Now click the "Edit" link on the widget over on the right
5. Set the colors you want (using full hex codes e.g. #1a2b3c), how many days you'd like the chart to be for, and whether or not you want the line to appear on non-home page charts.
6. Click "Done" and then save your changes.
7. Refresh your blog and bask in the glory that is Sparkplug!

= Template Tag =
Sparkplug comes with a simple template tag that you can use in any template file in your theme. It gives you a little more control over the output as well, in case you want to do something a little different.

Use this to output the default Sparkplug:

`<?php sparkplug() ?>`

There are a number of options you can use to configure the output, which are passed as an associative array like this:

`<?php sparkplug( array( 'barColor' => '#ff0000' ) ) ?>`

The full list of available options is:

 - do_total = bool -- should we display a line showing the total number of posts per day
 - days = int -- how many days of data should we show
 - barColor = hex -- the color of the bars on the chart
 - barWidth = int -- how many pixels wide should the bars be
 - barHeight = string -- CSS-style specification of the height of the chart (e.g. 20px)
 - minSpotColor = hex/bool -- a hex value to show a colored spot on the lowest point in the chart, or false to show none
 - maxSpotColor = hex/bool -- a la minSpotColor
 - spotRadius = int -- how many pixels wide should we draw those dots. 2 - 4 is usually good
 - fillColor = hex/bool -- a hex value to color in underneath the line, or false to disable [recommened]
 - lineColor = hex -- what color should the line be
 - defaultPixelsPerValue = int -- how many pixels between values

== Changelog ==

= 1.1 =
 - Updated version of jQuery Sparkline plugin packaged. Compatible with latest jQuery
 - Fixed defaults in sparkplug() so you can call it without a parameter

= 1.0 =
 - Initial release