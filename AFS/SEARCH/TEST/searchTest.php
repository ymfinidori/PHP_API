<?php ob_start();
require_once 'AFS/SEARCH/afs_search.php';

class SearchTest extends PHPUnit_Framework_TestCase
{
    public function testRetrieveDefaultParameters()
    {
        $search = new AfsSearch('127.0.0.1', 666);

        $service = $search->get_service();
        $this->assertEquals(666, $service->id);
        $this->assertEquals(AfsServiceStatus::STABLE, $service->status);

        $search->execute();
        $url = $search->get_generated_url();
        $this->assertTrue(strpos($url, '127.0.0.1') !== False, 'URL does not contain right host');
        $this->assertTrue(strpos($url, 'service=666') !== False, 'URL does not contain right sesrvice id');
        $this->assertTrue(strpos($url, 'status=stable') !== False, 'URL does not contain right sesrvice status');

        $config = $search->get_helpers_configuration();
        $this->assertEquals(AfsHelperFormat::HELPERS, $config->get_helper_format());
    }

    public function testRetrieveSpecificParameters()
    {
        $search = new AfsSearch('127.0.0.2', 42, AfsServiceStatus::RC);

        $service = $search->get_service();
        $this->assertEquals(42, $service->id);
        $this->assertEquals(AfsServiceStatus::RC, $service->status);

        $search->execute(AfsHelperFormat::ARRAYS);
        $url = $search->get_generated_url();
        $this->assertTrue(strpos($url, '127.0.0.2') !== False, 'URL does not contain right host');
        $this->assertTrue(strpos($url, 'service=42') !== False, 'URL does not contain right sesrvice id');
        $this->assertTrue(strpos($url, 'status=rc') !== False, 'URL does not contain right sesrvice status');

        $config = $search->get_helpers_configuration();
        $this->assertEquals(AfsHelperFormat::ARRAYS, $config->get_helper_format());
    }

    public function testSetQuery()
    {
        $search = new AfsSearch('127.0.0.1', 666);
        $query = new AfsQuery();
        $query = $query->set_query('foo');
        $search->set_query($query);

        $this->assertEquals('foo', $search->get_query()->get_query());

        $search->execute();
        $this->assertTrue(strpos($search->get_generated_url(), 'query=foo') !== False, 'URL does not contain query!');
    }
}
