<?php
/* vim: set expandtab sw=4 ts=4 sts=4: */
/**
 * Selenium TestCase for tracking related tests
 *
 * @package    PhpMyAdmin-test
 * @subpackage Selenium
 */

require_once 'TestBase.php';

/**
 * PmaSeleniumTrackingTest class
 *
 * @package    PhpMyAdmin-test
 * @subpackage Selenium
 * @group      selenium
 */
class PMA_SeleniumTrackingTest extends PMA_SeleniumBase
{
    /**
     * Setup the browser environment to run the selenium test case
     *
     * @return void
     */
    public function setUp()
    {
        parent::setUp();
        $this->dbQuery(
            "CREATE TABLE `test_table` ("
            . " `id` int(11) NOT NULL AUTO_INCREMENT,"
            . " `val` int(11) NOT NULL,"
            . " PRIMARY KEY (`id`)"
            . ")"
        );
        $this->dbQuery(
            "CREATE TABLE `test_table_2` ("
            . " `id` int(11) NOT NULL AUTO_INCREMENT,"
            . " `val` int(11) NOT NULL,"
            . " PRIMARY KEY (`id`)"
            . ")"
        );
        $this->dbQuery(
            "INSERT INTO `test_table` (val) VALUES (2), (3);"
        );
    }

    /**
     * setUp function that can use the selenium session (called before each test)
     *
     * @return void
     */
    public function setUpPage()
    {
        parent::setUpPage();

        $this->login();
        $this->skipIfNotPMADB();

        $this->waitForElement('byPartialLinkText', $this->database_name)->click();
        $this->waitForElement(
            "byXPath",
            "//a[@class='item' and contains(., 'Database: "
            . $this->database_name . "')]"
        );
        $this->waitForElementNotPresent('byId', 'ajax_message_num_1');
        $this->expandMore();

        $this->waitForElement('byPartialLinkText', "Tracking")->click();
        $this->waitForElementNotPresent('byId', 'ajax_message_num_1');

        usleep(1000000);
        $this->waitForElement("byPartialLinkText", "Track table");
        $ele = $this->byXPath("(//a[contains(., 'Track table')])[1]");

        $ele->click();
        // Let the click go through
        // If click happened before complete page load, it didn't go through
        while (! $this->isElementPresent("byName", "delete")
            && $this->isElementPresent('byXPath', "(//a[contains(., 'Track table')])[1]")
            && ! $this->isElementPresent('byId', 'ajax_message_num_1')
        ) {
            usleep(1000000);
            $ele->click();
        }

        $this->waitForElementNotPresent('byId', 'ajax_message_num_1');
        $this->waitForElement("byName", "delete")->click();
        $this->byCssSelector("input[value='Create version']")->click();
        $this->waitForElement("byId", "versions");
    }

    /**
     * Tests basic tracking functionality
     *
     * @return void
     *
     * @group large
     */
    public function testTrackingData()
    {
        $this->_executeSqlAndReturnToTableTracking();

        $this->byPartialLinkText("Tracking report")->click();
        $this->waitForElement(
            "byXPath",
            "//h3[contains(., 'Tracking report')]"
        );

        $this->assertContains(
            "DROP TABLE IF EXISTS `test_table`",
            $this->getCellByTableId('ddl_versions', 1, 4)
        );

        $this->assertContains(
            "CREATE TABLE `test_table` (",
            $this->getCellByTableId('ddl_versions', 2, 4)
        );

        $this->assertContains(
            "UPDATE test_table SET val = val + 1",
            $this->getCellByTableId('dml_versions', 1, 4)
        );

        $this->assertNotContains(
            "DELETE FROM test_table WHERE val = 3",
            $this->byId("dml_versions")->text()
        );

        // only structure
        $this->select($this->byName("logtype"))
            ->selectOptionByLabel("Structure only");
        $this->byCssSelector("input[value='Go']")->click();

        $this->waitForElementNotPresent("byId", "loading_parent");

        $this->assertFalse(
            $this->isElementPresent("byId", "dml_versions")
        );

        $this->assertContains(
            "DROP TABLE IF EXISTS `test_table`",
            $this->getCellByTableId('ddl_versions', 1, 4)
        );

        $this->assertContains(
            "CREATE TABLE `test_table` (",
            $this->getCellByTableId('ddl_versions', 2, 4)
        );

        // only data
        $this->select($this->waitForElement('byName', "logtype"))
            ->selectOptionByLabel("Data only");
        $this->byCssSelector("input[value='Go']")->click();

        $this->waitForElementNotPresent("byId", "ajax_message_num_1");
        usleep(1000000);

        $this->assertFalse(
            $this->isElementPresent("byId", "ddl_versions")
        );

        $this->assertContains(
            "UPDATE test_table SET val = val + 1",
            $this->getCellByTableId('dml_versions', 1, 4)
        );

        $this->assertNotContains(
            "DELETE FROM test_table WHERE val = 3",
            $this->byId("dml_versions")->text()
        );
    }

    /**
     * Tests deactivation of tracking
     *
     * @return void
     *
     * @group large
     */
    public function testDeactivateTracking()
    {
        $this->byCssSelector("input[value='Deactivate now']")->click();
        $this->waitForElement(
            "byCssSelector", "input[value='Activate now']"
        );
        $this->_executeSqlAndReturnToTableTracking();
        $this->assertFalse(
            $this->isElementPresent("byId", "dml_versions")
        );
    }

    /**
     * Tests dropping a tracking
     *
     * @return void
     *
     * @group large
     */
    public function testDropTracking()
    {
        $this->waitForElement(
            'byPartialLinkText',
            "Database: " . $this->database_name
        )->click();
        $this->waitForElementNotPresent('byId', 'ajax_message_num_1');
        $this->waitForElement("byId", "structureTable");

        $this->expandMore();
        usleep(1000000);

        $this->byPartialLinkText("Tracking")->click();

        $this->waitForElementNotPresent('byId', 'ajax_message_num_1');
        $this->waitForElement("byId", "versions");

        $ele = $this->waitForElement(
            'byCssSelector',
            'table#versions tbody tr:nth-child(1) td:nth-child(7)'
        );
        $this->moveto($ele);
        $this->sleep();
        $this->click();

        $this->sleep();
        $this->waitForElement(
            "byCssSelector",
            "button.submitOK"
        )->click();

        $this->waitForElementNotPresent('byId', 'ajax_message_num_1');
        $this->waitForElement(
            "byXPath",
            "//div[@class='success' and contains(., "
            . "'Tracking data deleted successfully.')]"
        );

        // Can not use getCellByTableId,
        // since this is under 'th' and not 'td'
        $this->assertContains(
            'test_table',
            $this->waitForElement(
                'byCssSelector',
                'table#noversions tbody tr:nth-child(1) th:nth-child(2)'
            )->text()
        );
        $this->assertContains(
            'test_table_2',
            $this->waitForElement(
                'byCssSelector',
                'table#noversions tbody tr:nth-child(2) th:nth-child(2)'
            )->text()
        );
    }

    /**
     * Tests structure snapshot of a tracking
     *
     * @return void
     *
     * @group large
     */
    public function testStructureSnapshot()
    {
        $this->byPartialLinkText("Structure snapshot")->click();
        $this->waitForElement("byId", "tablestructure");

        $this->assertContains(
            "id",
            $this->getCellByTableId('tablestructure', 1, 2)
        );

        $this->assertContains(
            "val",
            $this->getCellByTableId('tablestructure', 2, 2)
        );

        $this->assertContains(
            "PRIMARY",
            $this->getCellByTableId('tablestructure_indexes', 1, 1)
        );

        $this->assertContains(
            "id",
            $this->getCellByTableId('tablestructure_indexes', 1, 5)
        );
    }

    /**
     * Goes to SQL tab, executes queries, returns to tracking page
     *
     * @return void
     */
    private function _executeSqlAndReturnToTableTracking()
    {
        $this->byPartialLinkText("SQL")->click();
        $this->waitForElementNotPresent('byId', 'ajax_message_num_1');

        usleep(1000000);
        $this->waitForElement("byId", "queryfieldscontainer");
        $this->typeInTextArea(
            ";UPDATE test_table SET val = val + 1; "
            . "DELETE FROM test_table WHERE val = 3",
            2
        );
        $this->byCssSelector("input[value='Go']")->click();
        $this->waitForElementNotPresent('byId', 'ajax_message_num_1');
        $this->waitForElement("byClassName", "success");

        $this->expandMore();
        $this->byPartialLinkText("Tracking")->click();
        $this->waitForElementNotPresent('byId', 'ajax_message_num_1');
        $this->waitForElement("byId", "versions");
    }
}
