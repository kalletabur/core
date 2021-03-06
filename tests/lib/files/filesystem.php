<?php
/**
 * ownCloud
 *
 * @author Robin Appelman
 * @copyright 2012 Robin Appelman icewind@owncloud.com
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU AFFERO GENERAL PUBLIC LICENSE
 * License as published by the Free Software Foundation; either
 * version 3 of the License, or any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU AFFERO GENERAL PUBLIC LICENSE for more details.
 *
 * You should have received a copy of the GNU Affero General Public
 * License along with this library.  If not, see <http://www.gnu.org/licenses/>.
 *
 */

namespace Test\Files;

class Filesystem extends \Test\TestCase {
	/**
	 * @var array tmpDirs
	 */
	private $tmpDirs = array();

	/** @var \OC\Files\Storage\Storage */
	private $originalStorage;

	/**
	 * @return array
	 */
	private function getStorageData() {
		$dir = \OC_Helper::tmpFolder();
		$this->tmpDirs[] = $dir;
		return array('datadir' => $dir);
	}

	protected function setUp() {
		parent::setUp();

		$this->originalStorage = \OC\Files\Filesystem::getStorage('/');
		\OC_User::setUserId('');
		\OC\Files\Filesystem::clearMounts();
	}

	protected function tearDown() {
		foreach ($this->tmpDirs as $dir) {
			\OC_Helper::rmdirr($dir);
		}
		\OC\Files\Filesystem::clearMounts();
		\OC\Files\Filesystem::mount($this->originalStorage, array(), '/');
		\OC_User::setUserId('');

		parent::tearDown();
	}

	public function testMount() {
		\OC\Files\Filesystem::mount('\OC\Files\Storage\Local', self::getStorageData(), '/');
		$this->assertEquals('/', \OC\Files\Filesystem::getMountPoint('/'));
		$this->assertEquals('/', \OC\Files\Filesystem::getMountPoint('/some/folder'));
		list(, $internalPath) = \OC\Files\Filesystem::resolvePath('/');
		$this->assertEquals('', $internalPath);
		list(, $internalPath) = \OC\Files\Filesystem::resolvePath('/some/folder');
		$this->assertEquals('some/folder', $internalPath);

		\OC\Files\Filesystem::mount('\OC\Files\Storage\Local', self::getStorageData(), '/some');
		$this->assertEquals('/', \OC\Files\Filesystem::getMountPoint('/'));
		$this->assertEquals('/some/', \OC\Files\Filesystem::getMountPoint('/some/folder'));
		$this->assertEquals('/some/', \OC\Files\Filesystem::getMountPoint('/some/'));
		$this->assertEquals('/some/', \OC\Files\Filesystem::getMountPoint('/some'));
		list(, $internalPath) = \OC\Files\Filesystem::resolvePath('/some/folder');
		$this->assertEquals('folder', $internalPath);
	}

	public function normalizePathData() {
		return array(
			array('/', ''),
			array('/', '/'),
			array('/', '//'),
			array('/', '/', false),
			array('/', '//', false),

			array('/path', '/path/'),
			array('/path/', '/path/', false),
			array('/path', 'path'),

			array('/foo/bar', '/foo//bar/'),
			array('/foo/bar/', '/foo//bar/', false),
			array('/foo/bar', '/foo////bar'),
			array('/foo/bar', '/foo/////bar'),
			array('/foo/bar', '/foo/bar/.'),
			array('/foo/bar', '/foo/bar/./'),
			array('/foo/bar/', '/foo/bar/./', false),
			array('/foo/bar', '/foo/bar/./.'),
			array('/foo/bar', '/foo/bar/././'),
			array('/foo/bar/', '/foo/bar/././', false),
			array('/foo/bar', '/foo/./bar/'),
			array('/foo/bar/', '/foo/./bar/', false),
			array('/foo/.bar', '/foo/.bar/'),
			array('/foo/.bar/', '/foo/.bar/', false),
			array('/foo/.bar/tee', '/foo/.bar/tee'),

			// Windows paths
			array('/', ''),
			array('/', '\\'),
			array('/', '\\', false),
			array('/', '\\\\'),
			array('/', '\\\\', false),

			array('/path', '\\path'),
			array('/path', '\\path', false),
			array('/path', '\\path\\'),
			array('/path/', '\\path\\', false),

			array('/foo/bar', '\\foo\\\\bar\\'),
			array('/foo/bar/', '\\foo\\\\bar\\', false),
			array('/foo/bar', '\\foo\\\\\\\\bar'),
			array('/foo/bar', '\\foo\\\\\\\\\\bar'),
			array('/foo/bar', '\\foo\\bar\\.'),
			array('/foo/bar', '\\foo\\bar\\.\\'),
			array('/foo/bar/', '\\foo\\bar\\.\\', false),
			array('/foo/bar', '\\foo\\bar\\.\\.'),
			array('/foo/bar', '\\foo\\bar\\.\\.\\'),
			array('/foo/bar/', '\\foo\\bar\\.\\.\\', false),
			array('/foo/bar', '\\foo\\.\\bar\\'),
			array('/foo/bar/', '\\foo\\.\\bar\\', false),
			array('/foo/.bar', '\\foo\\.bar\\'),
			array('/foo/.bar/', '\\foo\\.bar\\', false),
			array('/foo/.bar/tee', '\\foo\\.bar\\tee'),

			// Absolute windows paths NOT marked as absolute
			array('/C:', 'C:\\'),
			array('/C:/', 'C:\\', false),
			array('/C:/tests', 'C:\\tests'),
			array('/C:/tests', 'C:\\tests', false),
			array('/C:/tests', 'C:\\tests\\'),
			array('/C:/tests/', 'C:\\tests\\', false),

			// normalize does not resolve '..' (by design)
			array('/foo/..', '/foo/../'),
			array('/foo/..', '\\foo\\..\\'),
		);
	}

	/**
	 * @dataProvider normalizePathData
	 */
	public function testNormalizePath($expected, $path, $stripTrailingSlash = true) {
		$this->assertEquals($expected, \OC\Files\Filesystem::normalizePath($path, $stripTrailingSlash));
	}

	public function isValidPathData() {
		return array(
			array('/', true),
			array('/path', true),
			array('/foo/bar', true),
			array('/foo//bar/', true),
			array('/foo////bar', true),
			array('/foo//\///bar', true),
			array('/foo/bar/.', true),
			array('/foo/bar/./', true),
			array('/foo/bar/./.', true),
			array('/foo/bar/././', true),
			array('/foo/bar/././..bar', true),
			array('/foo/bar/././..bar/a', true),
			array('/foo/bar/././..', false),
			array('/foo/bar/././../', false),
			array('/foo/bar/.././', false),
			array('/foo/bar/../../', false),
			array('/foo/bar/../..\\', false),
			array('..', false),
			array('../', false),
			array('../foo/bar', false),
			array('..\foo/bar', false),
		);
	}

	/**
	 * @dataProvider isValidPathData
	 */
	public function testIsValidPath($path, $expected) {
		$this->assertSame($expected, \OC\Files\Filesystem::isValidPath($path));
	}

	public function normalizePathWindowsAbsolutePathData() {
		return array(
			array('C:/', 'C:\\'),
			array('C:/', 'C:\\', false),
			array('C:/tests', 'C:\\tests'),
			array('C:/tests', 'C:\\tests', false),
			array('C:/tests', 'C:\\tests\\'),
			array('C:/tests/', 'C:\\tests\\', false),
		);
	}

	/**
	 * @dataProvider normalizePathWindowsAbsolutePathData
	 */
	public function testNormalizePathWindowsAbsolutePath($expected, $path, $stripTrailingSlash = true) {
		if (!\OC_Util::runningOnWindows()) {
			$this->markTestSkipped('This test is Windows only');
		}

		$this->assertEquals($expected, \OC\Files\Filesystem::normalizePath($path, $stripTrailingSlash, true));
	}

	public function testNormalizePathUTF8() {
		if (!class_exists('Patchwork\PHP\Shim\Normalizer')) {
			$this->markTestSkipped('UTF8 normalizer Patchwork was not found');
		}

		$this->assertEquals("/foo/bar\xC3\xBC", \OC\Files\Filesystem::normalizePath("/foo/baru\xCC\x88"));
		$this->assertEquals("/foo/bar\xC3\xBC", \OC\Files\Filesystem::normalizePath("\\foo\\baru\xCC\x88"));
	}

	public function testHooks() {
		if (\OC\Files\Filesystem::getView()) {
			$user = \OC_User::getUser();
		} else {
			$user = $this->getUniqueID();
			\OC\Files\Filesystem::init($user, '/' . $user . '/files');
		}
		\OC_Hook::clear('OC_Filesystem');
		\OC_Hook::connect('OC_Filesystem', 'post_write', $this, 'dummyHook');

		\OC\Files\Filesystem::mount('OC\Files\Storage\Temporary', array(), '/');

		$rootView = new \OC\Files\View('');
		$rootView->mkdir('/' . $user);
		$rootView->mkdir('/' . $user . '/files');

//		\OC\Files\Filesystem::file_put_contents('/foo', 'foo');
		\OC\Files\Filesystem::mkdir('/bar');
//		\OC\Files\Filesystem::file_put_contents('/bar//foo', 'foo');

		$tmpFile = \OC_Helper::tmpFile();
		file_put_contents($tmpFile, 'foo');
		$fh = fopen($tmpFile, 'r');
//		\OC\Files\Filesystem::file_put_contents('/bar//foo', $fh);
	}

	/**
	 * Tests that a local storage mount is used when passed user
	 * does not exist.
	 */
	public function testLocalMountWhenUserDoesNotExist() {
		$datadir = \OC_Config::getValue("datadirectory", \OC::$SERVERROOT . "/data");
		$userId = $this->getUniqueID('user_');

		\OC\Files\Filesystem::initMountPoints($userId);

		$homeMount = \OC\Files\Filesystem::getStorage('/' . $userId . '/');

		$this->assertTrue($homeMount->instanceOfStorage('\OC\Files\Storage\Local'));
		$this->assertEquals('local::' . $datadir . '/' . $userId . '/', $homeMount->getId());
	}

	/**
	 * Tests that the home storage is used for the user's mount point
	 */
	public function testHomeMount() {
		$userId = $this->getUniqueID('user_');

		\OC_User::createUser($userId, $userId);

		\OC\Files\Filesystem::initMountPoints($userId);

		$homeMount = \OC\Files\Filesystem::getStorage('/' . $userId . '/');

		$this->assertTrue($homeMount->instanceOfStorage('\OC\Files\Storage\Home'));
		$this->assertEquals('home::' . $userId, $homeMount->getId());

		\OC_User::deleteUser($userId);
	}

	/**
	 * Tests that the home storage is used in legacy mode
	 * for the user's mount point
	 */
	public function testLegacyHomeMount() {
		$datadir = \OC_Config::getValue("datadirectory", \OC::$SERVERROOT . "/data");
		$userId = $this->getUniqueID('user_');

		// insert storage into DB by constructing it
		// to make initMountsPoint find its existence
		$localStorage = new \OC\Files\Storage\Local(array('datadir' => $datadir . '/' . $userId . '/'));
		// this will trigger the insert
		$cache = $localStorage->getCache();

		\OC_User::createUser($userId, $userId);
		\OC\Files\Filesystem::initMountPoints($userId);

		$homeMount = \OC\Files\Filesystem::getStorage('/' . $userId . '/');

		$this->assertTrue($homeMount->instanceOfStorage('\OC\Files\Storage\Home'));
		$this->assertEquals('local::' . $datadir . '/' . $userId . '/', $homeMount->getId());

		\OC_User::deleteUser($userId);
		// delete storage entry
		$cache->clear();
	}

	public function dummyHook($arguments) {
		$path = $arguments['path'];
		$this->assertEquals($path, \OC\Files\Filesystem::normalizePath($path)); //the path passed to the hook should already be normalized
	}

	/**
	 * Test that the default cache dir is part of the user's home
	 */
	public function testMountDefaultCacheDir() {
		$userId = $this->getUniqueID('user_');
		$oldCachePath = \OC_Config::getValue('cache_path', '');
		// no cache path configured
		\OC_Config::setValue('cache_path', '');

		\OC_User::createUser($userId, $userId);
		\OC\Files\Filesystem::initMountPoints($userId);

		$this->assertEquals(
			'/' . $userId . '/',
			\OC\Files\Filesystem::getMountPoint('/' . $userId . '/cache')
		);
		list($storage, $internalPath) = \OC\Files\Filesystem::resolvePath('/' . $userId . '/cache');
		$this->assertTrue($storage->instanceOfStorage('\OC\Files\Storage\Home'));
		$this->assertEquals('cache', $internalPath);
		\OC_User::deleteUser($userId);

		\OC_Config::setValue('cache_path', $oldCachePath);
	}

	/**
	 * Test that an external cache is mounted into
	 * the user's home
	 */
	public function testMountExternalCacheDir() {
		$userId = $this->getUniqueID('user_');

		$oldCachePath = \OC_Config::getValue('cache_path', '');
		// set cache path to temp dir
		$cachePath = \OC_Helper::tmpFolder() . '/extcache';
		\OC_Config::setValue('cache_path', $cachePath);

		\OC_User::createUser($userId, $userId);
		\OC\Files\Filesystem::initMountPoints($userId);

		$this->assertEquals(
			'/' . $userId . '/cache/',
			\OC\Files\Filesystem::getMountPoint('/' . $userId . '/cache')
		);
		list($storage, $internalPath) = \OC\Files\Filesystem::resolvePath('/' . $userId . '/cache');
		$this->assertTrue($storage->instanceOfStorage('\OC\Files\Storage\Local'));
		$this->assertEquals('', $internalPath);
		\OC_User::deleteUser($userId);

		\OC_Config::setValue('cache_path', $oldCachePath);
	}
}
