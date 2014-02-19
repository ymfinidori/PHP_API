<?php ob_start();
require_once 'COMMON/afs_connector_interface.php';
require_once 'AFS/SEARCH/afs_search_query_manager.php';
require_once 'AFS/SEARCH/afs_query.php';
require_once 'AFS/SEARCH/afs_helper_configuration.php';

class ConnectorMock implements AfsConnectorInterface
{
    private $parameters = null;

    public function send(array $parameters)
    {
        $this->parameters = $parameters;
    }

    public function get_parameters()
    {
        return $this->parameters;
    }
}


class SearchQueryManagerTest extends PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        $this->connector = new ConnectorMock();
        $this->config = new AfsHelperConfiguration();
        $this->facet_mgr = $this->config->get_facet_manager();
        $this->qm = new AfsSearchQueryManager($this->connector, $this->config);
    }

    private function checkOneFacetValue($facet_id, $facet_value)
    {
        $params = $this->connector->get_parameters();
        $filter_str = explode('=', $params['afs:filter'][0], 2);
        $filter[$filter_str[0]] = $filter_str[1];
        $facet = $filter[$facet_id];
        $this->assertEquals($facet_value, $facet);
    }

    private function checkFacetValues($facet_id, $facet_values, $split)
    {
        $params = $this->connector->get_parameters();
        $filters = explode(' ' . $split . ' ', $params['afs:filter'][0]);
        $facets = array();
        foreach ($filters as $filter)
        {
            $facet_str = explode('=', $filter, 2);
            if (empty($facets[$facet_str[0]]))
            {
                $facets[$facet_str[0]] = array();
            }
            $facets[$facet_str[0]][] = $facet_str[1];
        }

        foreach ($facet_values as $value)
        {
            $this->assertTrue(in_array($value, $facets[$facet_id]));
        }
    }

    private function checkFromValue($origin)
    {
        $params = $this->connector->get_parameters();
        $this->assertTrue(array_key_exists('afs:from', $params));
        $this->assertEquals($origin, $params['afs:from']);
    }

    private function checkFacetDefaultValues($values)
    {
        $params = $this->connector->get_parameters();
        $this->assertTrue(array_key_exists('afs:facetDefault', $params));
        foreach ($values as $value)
            $this->assertTrue(in_array($value, $params['afs:facetDefault']));
    }

    private function checkFacetOptions($facet_id, $option)
    {
        $params = $this->connector->get_parameters();
        $this->assertTrue(array_key_exists('afs:facet', $params), 'No facet option available');
        $facet_options = array();
        foreach ($params['afs:facet'] as $facet_option) {
            $res = explode(',', $facet_option);
            $facet_options[$res[0]] = $res[1];
        }
        $this->assertTrue(array_key_exists($facet_id, $facet_options), 'No facet option available for facet: ' . $facet_id);
        $this->assertEquals($option, $facet_options[$facet_id]);
    }


    public function testNoParameterProvided()
    {
        $query = new AfsQuery();
        $this->qm->send($query);
        $this->checkFacetDefaultValues(array('replies=1000'));
    }

    public function testUnregisteredFacet()
    {
        $query = new AfsQuery();
        $query = $query->add_filter('foo', 'bar');
        $this->qm->send($query);
    }

    public function testOneFacetOneValue()
    {
        $facet = new AfsFacet('foo', AfsFacetType::STRING_TYPE);
        $this->facet_mgr->add_facet($facet);

        $query = new AfsQuery();
        $query = $query->add_filter('foo', '"bar"');
        $this->qm->send($query);
        $this->checkOneFacetValue('foo', '"bar"');
    }

    public function testFailOneFacetOneValue()
    {
        $facet = new AfsFacet('foo', AfsFacetType::INTEGER_TYPE);
        $this->facet_mgr->add_facet($facet);

        $query = new AfsQuery();
        $query = $query->add_filter('foo', 'bar');
        $this->qm->send($query);
        try
        {
            $this->checkOneFacetValue('foo', '"bar"');
        }
        catch (Exception $e)
        {
            return;
        }
        $this->fail('Should have failed due to value type/reference provided!');
    }

    public function testOneFacetMultipleValues()
    {
        $facet = new AfsFacet('foo', AfsFacetType::INTEGER_TYPE, AfsFacetLayout::TREE, AfsFacetMode::ADD, AfsFacetCombination::OR_MODE);
        $this->facet_mgr->add_facet($facet);

        $query = new AfsQuery();
        $query = $query->add_filter('foo', '4');
        $query = $query->add_filter('foo', '2');
        $this->qm->send($query);
        $this->checkFacetValues('foo', array('4', '2'), 'or');
    }

    public function testFailOnValueOneFacetMultipleValues()
    {
        $facet = new AfsFacet('foo', AfsFacetType::INTEGER_TYPE, AfsFacetLayout::TREE, AfsFacetMode::ADD, AfsFacetCombination::OR_MODE);
        $this->facet_mgr->add_facet($facet);

        $query = new AfsQuery();
        $query = $query->add_filter('foo', '4');
        $query = $query->add_filter('foo', '2');
        $this->qm->send($query);
        try
        {
            $this->checkFacetValues('foo', array('4', '3'), 'or');
        }
        catch (Exception $e)
        {
            return;
        }
        $this->fail('Should have failed due to invalid value provided!');
    }

    public function testFailOnModeValueOneFacetMultipleValues()
    {
        $facet = new AfsFacet('foo', AfsFacetType::INTEGER_TYPE, AfsFacetLayout::TREE, AfsFacetMode::ADD, AfsFacetCombination::OR_MODE);
        $this->facet_mgr->add_facet($facet);

        $query = new AfsQuery();
        $query = $query->add_filter('foo', '4');
        $query = $query->add_filter('foo', '2');
        $this->qm->send($query);
        try
        {
            $this->checkFacetValues('foo', array('4', '2'), 'and');
        }
        catch (Exception $e)
        {
            return;
        }
        $this->fail('Should have failed due to invalid mode provided!');
    }

    public function testFromParameter()
    {
        $facet = new AfsFacet('foo', AfsFacetType::INTEGER_TYPE, AfsFacetLayout::TREE, AfsFacetMode::ADD, AfsFacetCombination::OR_MODE);
        $this->facet_mgr->add_facet($facet);

        $query = new AfsQuery();
        $query = $query->auto_set_from()
                       ->add_filter('foo', '4')
                       ->add_filter('foo', '2');
        $this->qm->send($query);
        $this->checkFromValue(AfsOrigin::FACET);
    }

    public function testFacetDefaultNonSticky()
    {
        $query = new AfsQuery();
        $this->qm->send($query);
        $this->checkFacetDefaultValues(array('sticky=false'));
    }
    public function testFacetDefaultSticky()
    {
        $query = new AfsQuery();
        $this->facet_mgr->set_facets_stickyness();
        $this->qm->send($query);
        $this->checkFacetDefaultValues(array('sticky=true'));
    }
    public function testFacetNonSticky()
    {
        $query = new AfsQuery();
        $this->facet_mgr->set_facet_stickyness('FOO', false);
        $this->qm->send($query);
        $this->checkFacetOptions('FOO', 'sticky=false');
    }
    public function testFacetSticky()
    {
        $query = new AfsQuery();
        $this->facet_mgr->set_facet_stickyness('FOO', true);
        $this->qm->send($query);
        $this->checkFacetOptions('FOO', 'sticky=true');
    }

    public function testFacetNonStrictOrder()
    {
        $query = new AfsQuery();
        $this->facet_mgr->set_facet_sort_order(array('FOO', 'BAR'), AfsFacetSort::SMOOTH);
        $this->qm->send($query);
        $params = $this->connector->get_parameters();
        $this->assertFalse(array_key_exists('afs:facetOrder', $params));
    }
    public function testFacetStrictOrder()
    {
        $query = new AfsQuery();
        $sort = array('FOO', 'BAR');
        $this->facet_mgr->set_facet_sort_order($sort, AfsFacetSort::STRICT);
        $this->qm->send($query);
        $params = $this->connector->get_parameters();
        $this->assertTrue(array_key_exists('afs:facetOrder', $params));
        $this->assertEquals(implode(',', $sort), $params['afs:facetOrder']);
    }
}
