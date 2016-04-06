# bugnplay-parser

This is a PHP application based on the [Laravel framework](https://laravel.com/) that reads data from [bugnplay.ch](http://www.bugnplay.ch/) projects and export them as a JSON file.

## What you get

The JSON file will contain an array representing projects with the following object structure:

- `uid`: a unique identifier for this project, like `1:2011113` or `2:2753`. The first part is the minisite version (see below) and the second is the ID in this version
- `year`: the year this project was submitted for as an integer
- `title`: the title of the project
- `age_category`: one of `kids`, `juniors` or `seniors`
- `cover_image`: absolute URL to the cover image on `bugnplay.ch` server. Some old projects don't have any image and get a value of `<null>`
- `url`: absolute URL to the project minisite on `bugnplay.ch`
- `description`: the quick description of the project
- `type`: one of `independent`, `classroom` or `matu`. Projects from 2007 get a value of `<null>`
- `category`: one of `audio-video`, `web-words-games` or `installation-robotic`
- `links`: an array of objects:
  - `url`: absolute URL for this resource
  - `type`: one of `<null>`, `website`, `video`, `audio`, `file` as defined on the project page. `<null>` values should probably be considered the same as `file`
  - `title`: a string given to this resource or `<null>` if none
- `technologies`: an array of strings for the technology tags found in the description
- `members`: an array of objects:
  - `name`: full name of the person (firstname and lastname not separated, as it apprears on `bugnplay.ch`)
  - `role`: one of `<null>`, `leader` or `coach`
  - `residence`: `<null>` if unknown or object:
    - `npa`: 4-number code for the town
    - `town`: name of the town as given on `bugnplay.ch`
    - `canton`: two-uppercase-letters code of the canton, like `JU` or `ZH`
    - `lat`: float value of the latitude for the npa
    - `lng`: float value of the longitude for the npa

## How it works

The script makes heavy use of [Guzzle](https://github.com/guzzle/guzzle) to fetch webpages and [Laravel Caching](https://laravel.com/docs/5.2/cache) to keep these requests to the bare minimum by caching the HTML for the next run of the script.

For each asked `year`, the script fetches the projects listing at `http://www.bugnplay.ch/fr/projets/<year>/` (I use the french version as it is my language but really it would change nothing as HTML code is the same and main terms are written in English for all languages).

We can loop through all `.projectItem` on the page to get the projects and read the `id` in the javascript call, the `title`, the `age_category` and the `cover_image`.

Then comes the tricky part. From 2007 to 2013 the projects used the same minisite platform hosted at `projects.bugnplay.ch` (This is version `1`).
In 2014 the system changed and the projects are now hosted at `www.bugnplay.ch` (This is version `2`).
But the worst part (for this parser) is that the information available (and most probably the information stored) is not the same:

- In 2007 the project categories were split in 7 categories that are now joined together. This script merges these together when reading 2007 projects
- In 2007 there was no project `type` attribute so we can't know under which type a project would fall. They get a `type` value of `<null>`
- From 2007 to 2013 the place were projects member live was not displayed online, so the `residence` attribute gets a value of `<null>`

Minsites for V1 are fetched at `http://projects.bugnplay.ch/bugnplay/library/project.php?project=<id>` and V2 at `http://www.bugnplay.ch/pms/fr/minisite/<id>/`.
V2 minisite use a javascript-based tabs navigation so every bit of information has already been fetched.
V1 projects require 2 extra queries to fetch *Technologies* and *Sources* tabs.

The main page of the minisite is used to get `url`, `description`, `type`, `category` and `members` data.
The sidebar contains the `links` and their titles.

The `technologies` tags are obtained by scanning the *Technologies* and *Sources* tabs for keywords.
It works for most keywords, but sometimes formatting issues (like extra-galactic non-breaking spaces or invisible UTF-8 characters) prevent them from being read correctly.
The biggest issue is with people stating that they *don't* use a technology.
As very few people fall under this case I decided not to do anything special to handle the case.

## How to use

You need [PHP](http://php.net/) and [Composer](https://getcomposer.org/) on your system.
Download the project and `composer install` the dependencies.

Now grab the [latest ZIP codes file](http://download.geonames.org/export/zip/) for Switzerland from [GeoNames](http://www.geonames.org/), extract it and copy `CH.txt` to `storage/app/postal_codes.txt` in the project.

Run `php artisan serve` to launch the local webserver on `localhost:8000`. From now there are two endpoints you can use:

- `/`: displays the projects data in a simple table with additional data to debug the `technologies` detection
- `/projects.json`: outpouts the projects data in JSON format

By default projects from 2015 will be fetched and displayed.
Use the `?years` query string to choose the years to use, separated by comas.
For example:

- `/projects.json?years=2012` to get projects for a single year
- `/projects.json?years=2015,2014,2013,2012,2011,2010,2009,2008,2007` to get all projects

You can then use the *save as* option of the browser to save the JSON file to your PC.

## Disclaimer

I wrote and used this script in a way that I condider making fair use of the `bugnplay.ch` webservers resources.
No query was made more that one time (everything is cached), only one query happened at a time (no async) and I didn't fetch all years at once.

**Use it at your own risks !**

## MIT License

Copyright (c) 2016 Clark Winkelmann

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.
