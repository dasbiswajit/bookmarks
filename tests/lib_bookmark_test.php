<?php

OC_App::loadApp('bookmarks');

use \OCA\Bookmarks\Controller\Lib\Bookmarks;

class Test_LibBookmarks_Bookmarks extends PHPUnit_Framework_TestCase {

	private $userid;

	/** @var Bookmarks */
	protected $libBookmarks;

	protected function setUp() {
		$this->userid = \OCP\User::getUser();

		$db = \OC::$server->getDb();
		$config = \OC::$server->getConfig();
		$l = \OC::$server->getL10N('bookmarks');
		$clientService = \OC::$server->getHTTPClientService();
		$logger = \OC::$server->getLogger();
		$this->libBookmarks = new OCA\Bookmarks\Controller\Lib\Bookmarks($db, $config, $l, $clientService, $logger);
	}

	function testAddBookmark() {
		$this->cleanDB();
		$this->assertCount(0, $this->libBookmarks->findBookmarks($this->userid, 0, 'id', [], true, -1));
		$this->libBookmarks->addBookmark($this->userid, 'http://nextcloud.com', 'Nextcloud project', ['nc', 'cloud'], 'An awesome project');
		$this->assertCount(1, $this->libBookmarks->findBookmarks($this->userid, 0, 'id', [], true, -1));
		$this->libBookmarks->addBookmark($this->userid, 'http://de.wikipedia.org/Ü', 'Das Ü', ['encyclopedia', 'lang'], 'A terrific letter');
		$this->assertCount(2, $this->libBookmarks->findBookmarks($this->userid, 0, 'id', [], true, -1));
	}

	function testFindBookmarks() {
		$this->cleanDB();
		$this->libBookmarks->addBookmark($this->userid, "http://www.google.de", "Google", array("one"), "PrivateNoTag", false);
		$this->libBookmarks->addBookmark($this->userid, "http://www.heise.de", "Heise", array("one", "two"), "PrivatTag", false);
		$this->libBookmarks->addBookmark($this->userid, "http://www.golem.de", "Golem", array("one"), "PublicNoTag", true);
		$this->libBookmarks->addBookmark($this->userid, "http://www.9gag.com", "9gag", array("two", "three"), "PublicTag", true);
		$outputPrivate = $this->libBookmarks->findBookmarks($this->userid, 0, "", [], true, -1, false);
		$this->assertCount(4, $outputPrivate);
		$outputPrivateFiltered = $this->libBookmarks->findBookmarks($this->userid, 0, "", ["one"], true, -1, false);
		$this->assertCount(3, $outputPrivateFiltered);
		$outputPublic = $this->libBookmarks->findBookmarks($this->userid, 0, "", [], true, -1, true);
		$this->assertCount(2, $outputPublic);
		$outputPublicFiltered = $this->libBookmarks->findBookmarks($this->userid, 0, "", ["two"], true, -1, true);
		$this->assertCount(1, $outputPublicFiltered);
	}

	function testFindBookmarksSelectAndOrFilteredTags() {
		$this->cleanDB();
		$secondUser = $this->userid . "andHisClone435";
		$this->libBookmarks->addBookmark($this->userid, "http://www.google.de", "Google", array("one"), "PrivateNoTag", false);
		$this->libBookmarks->addBookmark($this->userid, "http://www.heise.de", "Heise", array("one", "two"), "PrivatTag", false);
		$this->libBookmarks->addBookmark($this->userid, "http://www.golem.de", "Golem", array("four"), "PublicNoTag", true);
		$this->libBookmarks->addBookmark($this->userid, "http://www.9gag.com", "9gag", array("two", "three"), "PublicTag", true);
		$this->libBookmarks->addBookmark($secondUser, "http://www.google.de", "Google", array("one"), "PrivateNoTag", false);
		$this->libBookmarks->addBookmark($secondUser, "http://www.heise.de", "Heise", array("one", "two"), "PrivatTag", false);
		$this->libBookmarks->addBookmark($secondUser, "http://www.golem.de", "Golem", array("four"), "PublicNoTag", true);
		$this->libBookmarks->addBookmark($secondUser, "http://www.9gag.com", "9gag", array("two", "three"), "PublicTag", true);
		$resultSetOne = $this->libBookmarks->findBookmarks($this->userid, 0, 'lastmodified', array('one', 'three'), true, -1, false, array('url', 'title'), 'or');
		$this->assertEquals(3, count($resultSetOne));
		$resultOne = $resultSetOne[0];
		$this->assertFalse(isset($resultOne['lastmodified']));
		$this->assertFalse(isset($resultOne['tags']));
	}

	function testFindTags() {
		$this->cleanDB();
		$this->assertEquals($this->libBookmarks->findTags($this->userid), array());
		$this->libBookmarks->addBookmark($this->userid, 'http://nextcloud.com', 'Nextcloud project', array('oc', 'cloud'), 'An awesome project');
		$this->assertEquals(array(0 => array('tag' => 'cloud', 'nbr' => 1), 1 => array('tag' => 'oc', 'nbr' => 1)), $this->libBookmarks->findTags($this->userid));
	}

	function testFindUniqueBookmark() {
		$this->cleanDB();
		$id = $this->libBookmarks->addBookmark($this->userid, "http://www.heise.de", "Heise", array("one", "two"), "PrivatTag", false);
		$bookmark = $this->libBookmarks->findUniqueBookmark($id, $this->userid);
		$this->assertEquals($id, $bookmark['id']);
		$this->assertEquals("Heise", $bookmark['title']);
	}

	function testEditBookmark() {
		$this->cleanDB();
		$id = $this->libBookmarks->addBookmark($this->userid, "http://www.heise.de", "Heise", array("one", "two"), "PrivatTag", false);
		$this->libBookmarks->editBookmark($this->userid, $id, "http://www.google.de", "NewTitle", array("three"));
		$bookmark = $this->libBookmarks->findUniqueBookmark($id, $this->userid);
		$this->assertEquals("NewTitle", $bookmark['title']);
		$this->assertEquals("http://www.google.de", $bookmark['url']);
		$this->assertEquals(1, count($bookmark['tags']));
	}

	function testDeleteBookmark() {
		$this->cleanDB();
		$this->libBookmarks->addBookmark($this->userid, "http://www.google.de", "Google", array("one"), "PrivateNoTag", false);
		$id = $this->libBookmarks->addBookmark($this->userid, "http://www.heise.de", "Heise", array("one", "two"), "PrivatTag", false);
		$this->assertNotEquals(false, $this->libBookmarks->bookmarkExists("http://www.google.de", $this->userid));
		$this->assertNotEquals(false, $this->libBookmarks->bookmarkExists("http://www.heise.de", $this->userid));
		$this->libBookmarks->deleteUrl($this->userid, $id);
		$this->assertFalse($this->libBookmarks->bookmarkExists("http://www.heise.de", $this->userid));
	}

	function testGetURLMetadata() {
		$amazonResponse = $this->createMock(\OCP\Http\Client\IResponse::class);
		$amazonResponse->expects($this->once())
			->method('getBody')
			->will($this->returnValue(file_get_contents(__DIR__ . '/res/amazonHtml.file')));
		$amazonResponse->expects($this->once())
			->method('getHeader')
			->with('Content-Type')
			->will($this->returnValue(''));

		$golemResponse = $this->createMock(\OCP\Http\Client\IResponse::class);
		$golemResponse->expects($this->once())
			->method('getBody')
			->will($this->returnValue(file_get_contents(__DIR__ . '/res/golemHtml.file')));
		$golemResponse->expects($this->once())
			->method('getHeader')
			->with('Content-Type')
			->will($this->returnValue('text/html; charset=UTF-8'));

		$clientMock = $this->createMock(\OCP\Http\Client\IClient::class);
		$clientMock->expects($this->exactly(2))
			->method('get')
			->will($this->returnCallback(function ($page) use($amazonResponse, $golemResponse) {
				if($page === 'amazonHtml') {
					return $amazonResponse;
				} else if($page === 'golemHtml') {
					return $golemResponse;
				}
			}));

		$clientServiceMock = $this->createMock(\OCP\Http\Client\IClientService::class);
		$clientServiceMock->expects($this->any())
			->method('newClient')
			->will($this->returnValue($clientMock));

		$this->registerHttpService($clientServiceMock);

		// ugly, but works
		$db = \OC::$server->getDb();
		$config = \OC::$server->getConfig();
		$l = \OC::$server->getL10N('bookmarks');
		$clientService = \OC::$server->getHTTPClientService();
		$logger = \OC::$server->getLogger();
		$this->libBookmarks = new OCA\Bookmarks\Controller\Lib\Bookmarks($db, $config, $l, $clientService, $logger);

		$metadataAmazon = $this->libBookmarks->getURLMetadata('amazonHtml');
		$this->assertTrue($metadataAmazon['url'] == 'amazonHtml');
		$this->assertTrue(strpos($metadataAmazon['title'], 'ü') !== false);

		$metadataGolem = $this->libBookmarks->getURLMetadata('golemHtml');
		$this->assertTrue($metadataGolem['url'] == 'golemHtml');
		$this->assertTrue(strpos($metadataGolem['title'], 'f&uuml;r') == false);
	}

	protected function tearDown() {
		$this->cleanDB();
	}

	function cleanDB() {
		$query1 = OC_DB::prepare('DELETE FROM *PREFIX*bookmarks');
		$query1->execute();
		$query2 = OC_DB::prepare('DELETE FROM *PREFIX*bookmarks_tags');
		$query2->execute();
	}

	/**
	 * Register an http service mock for testing purposes.
	 *
	 * @param \OCP\Http\Client\IClientService $service
	 */
	private function registerHttpService($service) {
		\OC::$server->registerService('HttpClientService', function () use ($service) {
			return $service;
		});
	}

}
