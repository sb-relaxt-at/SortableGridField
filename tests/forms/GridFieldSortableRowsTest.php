<?php

namespace UndefinedOffset\SortableGridField\Tests;

use SilverStripe\Control\HTTPRequest;
use SilverStripe\Control\Session;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Dev\TestOnly;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\ValidationException;
use SilverStripe\Security\Member;
use SilverStripe\Versioned\Versioned;
use UndefinedOffset\SortableGridField\Forms\GridFieldSortableRows;

class GridFieldSortableRowsTest extends SapphireTest
{
    /** @var ArrayList */
    protected $list;

    /** @var GridField */
    protected $gridField;

    /** @var Form */
    protected $form;

    /** @var string */
    public static $fixture_file = 'GridFieldSortableRowsTest.yml';

    /** @var array */
    protected static $extra_dataobjects = array(
        GridFieldAction_SortOrder_Team::class,
        GridFieldAction_SortOrder_VTeam::class
    );

    public function setUp()
    {
        parent::setUp();
        $this->list = GridFieldAction_SortOrder_Team::get();
        $config = GridFieldConfig::create()->addComponent(new GridFieldSortableRows('SortOrder'));
        $this->gridField = new GridField('testfield', 'testfield', $this->list, $config);
        $this->form = new Form(new SortableGridField_DummyController(), 'mockform', new FieldList(array($this->gridField)), new FieldList());
    }

    public function testSortActionWithoutCorrectPermission()
    {
        if (Member::currentUser()) {
            Member::currentUser()->logOut();
        }
        $this->setExpectedException(ValidationException::class);
        $team1 = $this->objFromFixture('UndefinedOffset\SortableGridField\Tests\GridFieldAction_SortOrder_Team', 'team1');
        $team2 = $this->objFromFixture('UndefinedOffset\SortableGridField\Tests\GridFieldAction_SortOrder_Team', 'team2');
        $team3 = $this->objFromFixture('UndefinedOffset\SortableGridField\Tests\GridFieldAction_SortOrder_Team', 'team3');

        $stateID = 'testGridStateActionField';
        $request = new HTTPRequest('POST', 'url', array('ItemIDs' => "$team1->ID, $team3->ID, $team2->ID"), array('action_gridFieldAlterAction?StateID=' . $stateID => true, $this->form->getSecurityToken()->getName() => $this->form->getSecurityToken()->getValue()));
        $session = Injector::inst()->create(Session::class, []);
        $request->setSession($session);
        $session->init($request);
        $session->set($stateID, array('grid' => '', 'actionName' => 'saveGridRowSort', 'args' => array('GridFieldSortableRows' => array('sortableToggle' => true))));
        $this->gridField->gridFieldAlterAction(array('StateID' => $stateID), $this->form, $request);
        $this->assertEquals($team3->ID, $this->list->last()->ID, 'User should\'t be able to sort records without correct permissions.');
    }

    public function testSortActionWithAdminPermission()
    {
        $team1 = $this->objFromFixture('UndefinedOffset\SortableGridField\Tests\GridFieldAction_SortOrder_Team', 'team1');
        $team2 = $this->objFromFixture('UndefinedOffset\SortableGridField\Tests\GridFieldAction_SortOrder_Team', 'team2');
        $team3 = $this->objFromFixture('UndefinedOffset\SortableGridField\Tests\GridFieldAction_SortOrder_Team', 'team3');
        $this->logInWithPermission('ADMIN');
        $stateID = 'testGridStateActionField';
        $request = new HTTPRequest('POST', 'url', array('ItemIDs' => "$team1->ID, $team3->ID, $team2->ID"), array('action_gridFieldAlterAction?StateID=' . $stateID => true, $this->form->getSecurityToken()->getName() => $this->form->getSecurityToken()->getValue()));
        $session = Injector::inst()->create(Session::class, []);
        $request->setSession($session);
        $session->init($request);
        $session->set($stateID, array('grid' => '', 'actionName' => 'saveGridRowSort', 'args' => array('GridFieldSortableRows' => array('sortableToggle' => true))));
        $this->gridField->gridFieldAlterAction(array('StateID' => $stateID), $this->form, $request);
        $this->assertEquals($team2->ID, $this->list->last()->ID, 'User should be able to sort records with ADMIN permission.');
    }

    public function testSortActionVersioned()
    {
        //Force versioned to reset
        Versioned::reset();

        $list = GridFieldAction_SortOrder_VTeam::get();
        $this->gridField->setList($list);

        /** @var GridFieldSortableRows $sortableGrid */
        $sortableGrid = $this->gridField->getConfig()->getComponentByType(GridFieldSortableRows::class);
        $sortableGrid->setUpdateVersionedStage('Live');

        //Publish all records
        foreach ($list as $item) {
            $item->publish('Stage', 'Live');
        }

        $team1 = $this->objFromFixture('UndefinedOffset\SortableGridField\Tests\GridFieldAction_SortOrder_VTeam', 'team1');
        $team2 = $this->objFromFixture('UndefinedOffset\SortableGridField\Tests\GridFieldAction_SortOrder_VTeam', 'team2');
        $team3 = $this->objFromFixture('UndefinedOffset\SortableGridField\Tests\GridFieldAction_SortOrder_VTeam', 'team3');

        $this->logInWithPermission('ADMIN');
        $stateID = 'testGridStateActionField';
        $request = new HTTPRequest('POST', 'url', array('ItemIDs' => "$team1->ID, $team3->ID, $team2->ID"), array('action_gridFieldAlterAction?StateID=' . $stateID => true, $this->form->getSecurityToken()->getName() => $this->form->getSecurityToken()->getValue()));
        $session = Injector::inst()->create(Session::class, []);
        $request->setSession($session);
        $session->init($request);
        $session->set($stateID, array('grid' => '', 'actionName' => 'saveGridRowSort', 'args' => array('GridFieldSortableRows' => array('sortableToggle' => true))));
        $this->gridField->gridFieldAlterAction(array('StateID' => $stateID), $this->form, $request);

        $this->assertEquals($team2->ID, $list->last()->ID, 'Sort should have happened on Versioned stage "Stage"');

        $list = Versioned::get_by_stage(GridFieldAction_SortOrder_VTeam::class, 'Live');
        $this->assertEquals($team2->ID, $list->last()->ID, 'Sort should have happened on Versioned stage "Live"');
    }
}

/**
 * Class GridFieldAction_SortOrder_Team
 *
 * @package SortableGridField\Tests
 * @property string Name
 * @property string City
 * @property int SortOrder
 */
class GridFieldAction_SortOrder_Team extends DataObject implements TestOnly
{
    private static $table_name = 'GridFieldAction_SortOrder_Team';

    private static $db = array(
        'Name' => 'Varchar',
        'City' => 'Varchar',
        'SortOrder' => 'Int'
    );

    private static $default_sort = 'SortOrder';
}

/**
 * Class GridFieldAction_SortOrder_VTeam
 *
 * @package SortableGridField\Tests
 * @property string Name
 * @property string City
 * @property int SortOrder
 */
class GridFieldAction_SortOrder_VTeam extends DataObject implements TestOnly
{
    private static $table_name = 'GridFieldAction_SortOrder_VTeam';

    private static $db = array(
        'Name' => 'Varchar',
        'City' => 'Varchar',
        'SortOrder' => 'Int'
    );
    private static $default_sort = 'SortOrder';

    private static $extensions = array(
        "SilverStripe\\Versioned\\Versioned('Stage', 'Live')"
    );
}
