<?php namespace App\Http\Controllers;

/**
 * bugnplay-parser
 * (c) 2016 Clark Winkelmann
 * @license MIT
 */

use Exception;
use GuzzleHttp\Client;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Contracts\Filesystem\Filesystem as Storage;
use Illuminate\Routing\Controller;
use Illuminate\Support\Collection;
use stdClass;
use Symfony\Component\DomCrawler\Crawler;

class ParserController extends Controller {

	public function __construct(Cache $cache, Storage $storage) {
		$this->cache = $cache;
		$this->storage = $storage;

		/**
		 * Array of technologies ids and matching words
		 * TODO: clean the list
		 */
		$this->technologies = [
			// remove handy ?
			// leGO PROviennent
			//'camera' => ['camera', 'kamera', 'caméra', 'handy', 'appareil photo', 'nintendo2ds', 'canon', 'nikon', 'quick cam logitech', 'logitech quick cam', 'megapixel', 'webcam', 'sony a77', 'gopro', 'go pro ', 'stativ', 'video aufnahme'],

			// Graphics editor
			'photoshop' => ['photoshop', 'fotoshop' /* typo */],
			'gimp' => ['gimp'],
			'photostage' => ['photostage'],
			'iphoto' => ['iphoto'],
			'inkscape' => ['inkscape'],
			'adobe-flash' => ['adobe flash', 'flash cs', 'actionscript'],
			'adobe-illustrator' => ['adobe illustrator'], // Adobe InDesign ?
			'graphicsgale' => ['graphicsgale'], // or video editor

			// Game/3D engines
			'blender' => ['blender'],
			'maya' => ['maya'],
			'sketchup' => ['sketchup', 'sketch up'],
			'cinema4d' => ['cinema4d', 'cinema 4d', 'c4d'],
			'ldraw' => ['ldraw'],
			'unity' => [' unity', '-unity', 'unity-'], // ->community
			'unreal-engine' => ['unreal engine'],
			'gdevelop' => ['gdevelop', 'game develop'],
			'game-maker' => ['game maker', 'gamemaker'],
			'3ds-max' => ['3ds max'], // (autodesk)
			'rpg-maker' => ['rpg maker'],
			'muvizu' => ['muvizu'],
			'iclone' => ['iclone'],

			// Video editor
			'imovie' => ['imovie', 'i movie', 'i move'],
			'windows-movie-maker' => ['windows movie maker', 'moviemaker', 'movie maker'],
			'adobe-premiere' => ['adobe premiere'],
			'after-effects' => ['after effect'],
			'istopmotion' => ['istopmotion', 'istop motion'],
			'sony-vegas' => ['sony vegas'],
			'finalcut' => ['finalcut', 'final cut'],
			'pinnacle-studio' => ['pinnacle', 'pinnacle studio' /* useless */],
			'camtasia' => ['camtasia', 'camptasia'],
			'animatordv' => ['animatordv', 'animator dv'],
			'magix-video-deluxe' => ['video deluxe'],
			'fraps' => ['fraps'],
			'quicktime' => ['quicktime', 'quick time'],
			'zu3d' => ['zu3d'],

			// Programming environments
			'scratch' => ['scratch'],
			'xcode' => ['xcode'],
			'visual-studio' => ['visual studio'],
			'mit-app-inventor' => ['mit app inventor'], // ?
			'labview' => ['labview'],
			'greenfoot' => ['greenfoot'], // -> java ? --
			'robolab' => ['robolab'],

			// Programming languages
			'python' => ['python'],
			'nxt-g' => ['nxt-g'], // place before lego ?
			'nxc' => ['nxc'],
			'swift' => ['swift'],
			'cpp' => ['c++'],
			'qt' => [' qt'], // warning
			'csharp' => ['csharp', 'c#', 'c-sharp', 'monodevelop' /* keep ? */],
			'objective-c' => ['objective-c'],
			'xlogo' => ['xlogo'], // ?
			'android' => ['android sdk', 'android api', 'adt plugin'],
			'java' => ['java ', 'java,', 'java)'], // Processing !!!!!javascript :not "java script" ?

			// LEGO
			//'lego-digital-designer' => ['lego digital designer'], // ?
			'lego' => ['lego'], // problem with Allegorithmic
			'playmobil' => ['playmobil'],

			// Web
			'php' => [' php', ':php', '/php'], // dont catch in urls
			'mysql' => ['mysql'],
			'javascript' => ['javascript', 'java script'],
			'xampp' => ['xampp'],
			'wordpress' => [' wordpress'],

			// Audio editor
			'audacity' => ['audacity'],
			'garageband' => ['garageband', 'garage band', 'garage-band'],
			'adobe-audition' => ['adobe audition'],
			'magix-music-maker' => ['magix music maker'],
			'tuxguitar' => ['tuxguitar'],
			'ableton-live' => ['ableton live'],
			'fl-studio' => ['fl studio'],
			'sibelius' => ['sibelius'], // first, 7
			'logic-pro' => ['logic pro'], // (apple)

			// Robotics
			'mindstorms' => ['mindstorm', 'nxt', 'ev3'], // rcx ?
			'arduino' => ['arduino'], // careful to people "not using it"
			'raspberry-pi' => ['raspberry pi'],
			'fritzing' => ['fritzing'],

			'stop-motion' => ['stopmotion', 'stop motion', 'stopmotin' /* with typo */, 'stop-motion'], // careful !

			//'notepadpp' => ['notepad++'],
			'inno-setup' => ['inno setup', 'innosetup'],
			//'subversion' => ['subversion'],
			//'git' => [' git', 'github'], // -> gitarre
			//'jekyll' => ['jekyll'],
			//'sass' => [' sass'], // collisions !
			'geogebra' => ['geogebra'],
			'mspowerpoint' => ['powerpoint', 'power point'],
			'msword' => ['office word'], // Word ?
			'crazy-talk-animator' => ['crazy talk animator'],
			// paint ?
			// IGunPro ?
			// MovieFX ???
			// Slick ?
			// SnagIt ?
			// Makehuman ?
			// Xilisoft, x media recode ?
			// Heroku ?
			// Balabolka ?
			// "langage C"
			// led ?
		];
	}

	/**
	 * Fetch an URL with Guzzle and put it in the cache so we don't DOS the server
	 */
	public function fetchUrl($url)
	{
		return $this->cache->rememberForever($url, function() use($url) {
			$client = new Client();
			$res = $client->get($url);
			return $res->getBody()->getContents();
		});
	}

	/**
	 * Look for technologies in the given text and replace them with HTML code
	 * Will be used to test if the technology list is useful
	 */
	public function analyseTechnologies($text)
	{
		// Replace nbsp with normal spaces to prevent problems
		$text = str_replace("\xc2\xa0", ' ', $text);

		$find = [];
		$replace = [];

		foreach($this->technologies as $title => $technology) {
			foreach($technology as $term) {
				$find[] = $term;
				$replace[] = '<b style="color: red;">' . $term . '[-> ' . $title . ']</b>';
			}
		}

		return str_ireplace($find, $replace, $text);
	}

	/**
	 * Look for technologies in the given text and return the list of technologies
	 */
	public function findTechnologies($text)
	{
		// Replace nbsp with normal spaces to prevent problems
		$text = str_replace("\xc2\xa0", ' ', $text);

		$technologies = [];

		foreach($this->technologies as $technology => $terms) {
			foreach($terms as $term) {
				if(stripos($text, $term) !== false) {
					$technologies[] = $technology;
					break; // exit this technology loop
				}
			}
		}

		return $technologies;
	}

	/**
	 * Load the ZIP codes from the GeoNames file into a ZIP-indiced array and return it
	 */
	public function postalCodes()
	{
		$codes = [];

		// Download the zip codes file at http://download.geonames.org/export/zip/
		foreach(explode("\n", $this->storage->get('postal_codes.txt')) as $line) {
			// Skip blank lines
			if(empty($line)) {
				continue;
			}

			$line_data = explode("\t", $line);

			$data = new stdClass();
			$data->latitude  = floatval($line_data[9]);
			$data->longitude = floatval($line_data[10]);

			$codes[$line_data[1]] = $data;
		}

		return $codes;
	}

	/**
	 * Does the actual parsing
	 * @param boolean $with_analysis Whether to add additional keys with analysed data
	 * @return Collection
	 */
	public function parse($with_analysis = false)
	{
		$years = [
			2015,
			2014,
			2013,
		];

		$projects = new Collection();

		$postal_codes = $this->postalCodes();

		foreach($years as $year) {
			/**
			 * The projects page will give the list of projects and basic information
			 */
			$crawler = new Crawler($this->fetchUrl('http://www.bugnplay.ch/fr/projets/' . $year . '/'), 'http://www.bugnplay.ch/');

			/**
			 * For each project listed
			 */
			$crawler->filter('.projectItem')->each(function(Crawler $node) use($projects, $year, $with_analysis, $postal_codes) {
				$project = new stdClass();

				$javascript_call = $node->filter('h3 a')->attr('href');

				/**
				 * Find the version used by the project
				 * `1` is the one used from 2007 to 2013
				 * `2` is the one used since 2014
				 * We can know the difference from the javascript call on the projects page
				 */
				$bnp_version = null;
				switch(true) {
					case str_contains($javascript_call, 'openProject'):
						$bnp_version = 1;
						break;
					case str_contains($javascript_call, 'openPWProject'):
						$bnp_version = 2;
						break;
					default:
						throw new Exception('Invalid project version');
				}

				/**
				 * Find the project ID. IDs for version `1` are very high while `2` are currently lower
				 * We can get it from the javascript call. Version 1 and 2 are very similar so one regexp catches both cases
				 */
				if(preg_match('/^javascript:[a-zA-Z]+\(([0-9]+)(\,[a-zA-Z\']+)?\)\;$/', $javascript_call, $matches) !== 1) {
					throw new Exception('Invalid project link: "' . $javascript_call . '"');
				}
				$id = $matches[1];

				/**
				 * UID is a globally unique identifier for the project in the form:
				 * `project version`:`project id`
				 */
				$project->uid = $bnp_version . ':' . $id;
				$project->year = $year;
				$project->title = trim($node->filter('h3 a')->text());

				/**
				 * Age category
				 * Has to be read from the listing page as v1 projects don't have the information on project page
				 */
				$project_age_text = $node->filter('.name .category')->text();
				switch(true) {
					case str_contains($project_age_text, 'Kids'):
						$project->age_category = 'kids';
						break;
					case str_contains($project_age_text, 'Juniors'):
						$project->age_category = 'juniors';
						break;
					case str_contains($project_age_text, 'Seniors'):
						$project->age_category = 'seniors';
						break;
					default:
						throw new Exception('Invalid age category "' . $project_age_text . '"');
				}
				
				/**
				 * Project cover image
				 * Get the small one from the listing to keep things small
				 */
				$cover_image_raw_url = $node->filter('img')->attr('src');
				$cover_image_url = (substr($cover_image_raw_url, 0, 1) == '/' ? 'http://www.bugnplay.ch' : '') . $cover_image_raw_url;
				// We could save the image in base64 but this is far too heavy
				// so let's store the link to the one on bugnplay server
				/*$image_data = $this->fetchUrl($cover_image_url);
				$image_ext = null;
				switch(true) {
					case str_contains($cover_image_url, '.jpg'):
					case str_contains($cover_image_url, '.jpeg'):
						$image_ext = 'jpeg';
						break;
					case str_contains($cover_image_url, '.png'):
						$image_ext = 'png';
						break;
					case str_contains($cover_image_url, '.gif'):
						$image_ext = 'gif';
						break;
					default:
						throw new Exception('Invalid image ext "' . $cover_image_url . '"');
				}
				$project->cover_image = 'data:image/' . $image_ext . ';base64,' . base64_encode($image_data);*/
				$project->cover_image = $cover_image_url;

				/**
				 * For the details we need to get the project page
				 */
				switch($bnp_version) {
					case 1:
						$project_page_url = 'http://projects.bugnplay.ch/bugnplay/library/project.php?project=' . $id;
						$project_page = new Crawler($this->fetchUrl($project_page_url), 'http://projects.bugnplay.ch/');

						/**
						 * Project description
						 * First p after the Overview title
						 */
						$description = $project_page->filter('#maincontent h2')->reduce(function(Crawler $node) {
							return $node->text() == 'Overview';
						})->nextAll()->first()->text();
						$project->description = str_replace("\r\n", "\n", trim($description));

						/**
						 * Project type and category
						 * They are in the same p, no problem to run a simple str_contains on it
						 */
						$category_text = $project_page->filter('#maincontent h2')->reduce(function(Crawler $node) {
							return $node->text() == 'Category';
						})->nextAll()->first()->html();
						switch(true) {
							case str_contains($category_text, 'Independent project'):
								$project->type = 'independent';
								break;
							case str_contains($category_text, 'Class room project'):
								$project->type = 'classroom';
								break;
							case str_contains($category_text, 'Maturaarbeit'):
								$project->type = 'matu';
								break;
							default:
								throw new Exception('Invalid project type "' . $category_text . '"');
						}
						switch(true) {
							case str_contains($category_text, 'Audio/Video'):
								$project->category = 'audio-video';
								break;
							case str_contains($category_text, 'Web/Words/Games'):
								$project->category = 'web-words-games';
								break;
							case str_contains($category_text, 'Installation/Robotic'):
								$project->category = 'installation-robotic';
								break;
							default:
								throw new Exception('Invalid project category "' . $category_text . '"');
						}

						/**
						 * Links
						 */
						$project->links = new Collection();

						// The official URL link sits in the main content
						$url_title_node = $project_page->filter('#maincontent h2')->reduce(function(Crawler $node) {
							return $node->text() == 'URL';
						});

						// Only if there is an official link
						if($url_title_node->count()) {
							$link = new stdClass();
							$link->url = $url_title_node->nextAll()->first()->filter('a')->link()->getUri();
							$link->type = 'website';
							$link->title = null;
							$project->links->push($link);
						}

						// Probably better not ask why the box has an id of #loginbox not why the table is inside an h1
						$project_page->filter('#loginbox tr')->each(function(Crawler $node) use($project) {
							$link = new stdClass();
							$link->url = $node->filter('a')->link()->getUri();

							// At this time all links had the same value
							$link->type = null;
							// We still add a video type to the video files for ease of use later
							if(str_contains($link->url, ['.mov', '.wmv'])) {
								$link->type = 'video';
							}

							// Title is the text until first br
							$link->title = trim(strstr($node->filter('td')->last()->html(), '<br>', true));
							// Don't leave a blank value, set null
							if($link->title == '') {
								$link->title = null;
							}

							$project->links->push($link);
						});

						/**
						 * Members
						 */
						$members_text = $project_page->filter('#maincontent h2')->reduce(function(Crawler $node) {
							return $node->text() == 'Autors';
						})->nextAll()->first()->text();

						$members_raw = explode(',', trim($members_text));

						$project->members = new Collection();

						$isfirst = true;
						foreach($members_raw as $member_raw) {
							$member = new stdClass();
							// Keep everything before parenthesis
							$member->name = trim(strstr($member_raw, '(', true));
							$member->role = $isfirst ? 'leader' : null; // The first person in the list is the leader
							$member->residence = null;
							$project->members->push($member);
							$isfirst = false;
						}

						// Coach is a separate section in v1
						$coach_text = trim($project_page->filter('#maincontent h2')->reduce(function(Crawler $node) {
							return $node->text() == 'Coach';
						})->nextAll()->first()->text());

						// Only if there is really a coach (blank otherwise)
						if($coach_text != '') {
							$coach = new stdClass();
							$coach->name = $coach_text;
							$coach->role = 'coach';
							$member->residence = null;
							$project->members->push($coach);
						}

						/**
						 * Technologies
						 */
						$technologies_page_url = $project_page_url . '&part=technologies';
						$technologies_page = new Crawler($this->fetchUrl($technologies_page_url), 'http://projects.bugnplay.ch/');
						$technologies_text = $technologies_page->filter('#maincontent')->text();
						$sources_page_url = $project_page_url . '&part=used_sources';
						$sources_page = new Crawler($this->fetchUrl($sources_page_url), 'http://projects.bugnplay.ch/');
						$technologies_text .= ' ----------- ' . $sources_page->filter('#maincontent')->text();
						$project->technologies = $this->findTechnologies($technologies_text);

						if($with_analysis) {
							$project->technologies_analysis = $this->analyseTechnologies($technologies_text);
						}

						break;
					case 2:
						$project_page_url = 'http://www.bugnplay.ch/pms/fr/minisite/' . $id . '/';
						$project_page = new Crawler($this->fetchUrl($project_page_url), 'http://www.bugnplay.ch/');

						/**
						 * Project description
						 * There is no pretty class or title to find the right paragraph...
						 * It is second when there is one picture, third when there is a line of pictures added
						 * The last one brefore the first h3 seems to do the trick
						 */
						$description = $project_page->filter('#block_overview h3')->first()->previousAll()->first()->text();
						$project->description = str_replace("\r\n", "\n", trim($description));

						/**
						 * Project type
						 * This one is simple, there are only three options
						 * They are named in English so everyone is happy
						 * (or at least everybody is as sad as everybody)
						 */
						$project_type_text = $project_page->filter('#block_overview h3')->reduce(function(Crawler $node) {
							return $node->text() == 'Type:';
						})->nextAll()->first()->text();
						switch($project_type_text) {
							case 'Projet de loisirs':
								$project->type = 'independent';
								break;
							case 'Projet de cours':
								$project->type = 'classroom';
								break;
							case 'Travail de maturité':
								$project->type = 'matu';
								break;
							default:
								throw new Exception('Invalid project type "' . $project_type_text . '"');
						}

						/**
						 * Project category
						 * Pretty much the same as for the project type
						 */
						$project_category_text = $project_page->filter('#block_overview h3')->reduce(function(Crawler $node) {
							return $node->text() == 'Catégorie:';
						})->nextAll()->first()->text();
						switch($project_category_text) {
							case 'Audio/Video':
								$project->category = 'audio-video';
								break;
							case 'Web/Words/Games':
								$project->category = 'web-words-games';
								break;
							case 'Installation/Robotic':
								$project->category = 'installation-robotic';
								break;
							default:
								throw new Exception('Invalid project category "' . $project_category_text . '"');
						}

						/**
						 * Links
						 */
						$project->links = new Collection();
						$project_page->filter('#left h3')->each(function(Crawler $node) use($project) {
							$link = new stdClass();
							$link->url = $node->nextAll()->filter('ul a')->link()->getUri();

							$link->type = null;
							switch($node->text()) {
								case 'Audio':
									$link->type = 'audio';
									break;
								case 'Video':
									$link->type = 'video';
									break;
								case 'File':
									$link->type = 'file';
									break;
								case 'URL':
									$link->type = 'website';
									break;
							}

							// Get the link title if any
							// If present, there is a p just after the h3
							$next_node = $node->nextAll()->first();
							if($next_node->nodeName() == 'p') {
								$link->title = trim($next_node->text());
							} else {
								$link->title = null;
							}

							$project->links->push($link);
						});

						$technologies_text = $project_page->filter('#block_technologies .user_content')->text();
						// Some people put interesting stuff in the source part, so scan this as well
						$technologies_text .= ' ----------- ' . $project_page->filter('#block_sources .user_content')->text();
						$project->technologies = $this->findTechnologies($technologies_text);

						if($with_analysis) {
							$project->technologies_analysis = $this->analyseTechnologies($technologies_text);
						}

						/**
						 * The list of members is placed in a single p that we need to parse
						 */
						$members_html = $project_page->filter('#block_overview h3')->reduce(function(Crawler $node) {
							return $node->text() == 'Ton groupe:';
						})->nextAll()->first()->text();

						$project->members = new Collection();

						// Remove special characters at each and and separate by linefeed
						foreach(explode("\r\n", trim($members_html)) as $member_line) {
							$member_line = trim($member_line);

							// Will be either:
							// Firstname Lastname (PL), 1234 Place (CANTON)
							// Firstname Lastname (Coach), 1234 Place (CANTON)
							// Firstname Lastname, 1234 Place (CANTON)
							if(preg_match('/^(.+?)( \([a-zA-Z]+\))?, ([0-9]+) (.+) \(([A-Z]{2})\)$/', $member_line, $matches) !== 1) {
								throw new Exception('Invalid member line: "' . $member_line . '"');
							}

							$member = new \stdClass();
							$member->name   = $matches[1];

							$role = null;
							switch(true) {
								case str_contains($matches[2], 'PL'):
									$role = 'leader';
									break;
								case str_contains($matches[2], 'Coach'):
									$role = 'coach';
									break;
							}

							$member->role   = $role;
							$member->residence = new stdClass();
							$member->residence->npa    = $matches[3];
							$member->residence->town   = $matches[4];
							$member->residence->canton = $matches[5];
							$member->residence->lat = $postal_codes[$member->residence->npa]->latitude;
							$member->residence->lng = $postal_codes[$member->residence->npa]->longitude;

							$project->members->push($member);
						}

						break;
				}

				$projects->push($project);
			});
		}

		return $projects;
	}

	/**
	 * Handle the request for /, which returns a webpage with the analysis
	 */
	public function getParser()
	{
		return view('parser', ['projects' => $this->parse(true)]);
	}

	/**
	 * Handle the request for /projects.json, which returns a pretty-formatted json file
	 */
	public function getJson()
	{
		return response(json_encode($this->parse(), JSON_PRETTY_PRINT), 200, ['Content-Type' => 'application/json']);
	}

}
