=== Loop Shortcode ===
Contributors: enej, ctlt-dev
Tags: 
Requires at least: 3.4
Tested up to: 3.4
Stable tag: 0.1

[loop] is a very powerful shortcode that allows you to customize the type of content to display.

With this shortcode you can do anything mentioned here; 
http://codex.wordpress.org/Function_Reference/query_posts

For more information on what kind of parameters are possible in the query parameter please see; 
http://codex.wordpress.org/Class_Reference/WP_Query#Parameters

== Usage ==

The loop shortcode accepts any of these parameters.
* query - required, the query that you want to retrieve from the database see [http://codex.wordpress.org/Function_Reference/query_posts some possibilities here]. 
* rss - the url of the feed that you want to display,
* view - choose one  (full, archive, list) or create your own. 
* pagination - by default pagination is false, only works with the query and not the rss.
* num - used in conjunction with the rss to display a limited set of item, 
* error - what to display if there is no results can be found
* author - filters posts by author name or for the current logged in user, use: author="current_user" #

'# for current user comments, use the [user_comments] shortcode

You can use the following attributes for the "view" parameter:
 * archive
 * list
 * full
 

== Example ==
Please find below several examples of [loop] usage. 

The following line placed in the main content area of the frontpage will display the latest 5 posts on the frontpage of the site:

 [loop query="posts_per_page=5" view="archive"]

The following line placed in the main content area of the frontpage will display the latest 5 posts on the frontpage of the site, with pagination enabled to navigate to older posts:

 [loop query="posts_per_page=5" pagination="true" view="archive"]

To combine parameters in the query, use the ampersand character ('&'). The following line displays 5 posts from the category SampleCategory:

 [loop query="posts_per_page=5&category_name=SampleCategory" view="archive"]

Below is another example of [loop] usage:

 [loop query="category_name=Conference"]
  <a href="[permalink]">[the_title]</a>
  [the_excerpt] by [the_author] - Date: [last-updated]
 [/loop]

This example pulls all posts in the category "Conference" and displays the title, permalink, author, date and excerpt and thumbnail image if available.


You can also sort by custom field. For example:

 [loop query="posts_per_page=5&category_name=newsandevents&meta_key=event_date&orderby=meta_value&order=asc" view="archive"]

You can also use loop shortcode with pages.

For example, if you wish to dynamically pull a list of pages that use custom field 'color', regardless of the value of that custom field, you might do it with the help of a shortcode that looks like this:

 [loop query="post_per_page=5&post_type=page&meta_key=color" view="list"]

To return all the posts in the February 2011, use the time parameters (see http://codex.wordpress.org/Class_Reference/WP_Query#Time_Parameters)

 [loop query="year=2011&monthnum=2" view=archive]

To return posts with future publishing dates in similar fashion as upcoming events use this: 

 [loop query="order=ASC&category_name=upcoming-events&post_status=future,publish"]

The loop query above will show future and published posts in ascending order. If you wanted to only show future posts delete publish from post_status. 
