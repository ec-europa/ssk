<?php

/**
 * Documentation generator.
 *
 * PHP Version 5 and 7
 *
 * @category BuildSystem
 * @package  DrupalToolkit
 * @author   DIGIT NEXTEUROPA QA <DIGIT-NEXTEUROPA-QA@ec.europa.eu>
 * @license  https://ec.europa.eu/info/european-union-public-licence_en EUPL
 * @link     https://github.com/ec-europa/toolkit/blob/master/includes/phing/src/Tasks/DocGeneratorTask.php
 */

namespace Phing\Toolkit\Tasks;

use Phing\Toolkit\Tasks\PhingHelpTask;
use Project;
use Symfony\Component\Finder\Finder;

require_once 'phing/Task.php';

/**
 * A Phing task to generate an aliases.drushrc.php file.
 *
 * @category BuildSystem
 * @package  DrupalToolkit
 * @author   DIGIT NEXTEUROPA QA <DIGIT-NEXTEUROPA-QA@ec.europa.eu>
 * @license  https://ec.europa.eu/info/european-union-public-licence_en EUPL
 * @link     https://github.com/ec-europa/toolkit/blob/master/includes/phing/src/Tasks/DocGeneratorTask.php
 */
class DocGeneratorTask extends \Task
{

    /**
     * The location of the build file to generate docs for.
     *
     * @var string
     */
    private $_buildfile = '';

    /**
     * Generates an aliases.drushrc.php file.
     *
     * Either generates a file for:
     *  - all sites in the sites directory.
     *  - a single site to be added to the aliases file (appending).
     *
     * @return void
     */
    public function main()
    {

        ini_set('xdebug.var_display_max_depth', 10);

        // $this->checkRequirements();
        $project   = $this->getProject();
        $buildList = PhingHelpTask::getBuildList($this->_buildFile);

        $targetsArray      = array();
        $wrapperTargets    = array();
        $buildTargets   = array();
        $callbackTargets   = array();
        $deprecatedTargets = array();
        $helperTargets     = array();

        foreach ($buildList as $buildFile => $info) {
            $xml = simplexml_load_file($buildFile);

            foreach ($xml->xpath('//target') as $target) {
                $targetName        = (string) $target->attributes()->name;
                $targetVisibility  = (string) $target->attributes()->hidden == 'true' ? 'hidden' : 'visible';
                $targetDescription = (string) $target->attributes()->description;

                $targetArray = array(
                    'name'        => $targetName,
                    'description' => $targetDescription,
                    'visibility'  => $targetVisibility,
                    'buildfile'   => $buildFile,
                );

                if (strpos($targetName, "build-") === 0) {
                        $targetDependenciesString = (string) $target->xpath(
                            './@depends'
                        )[0];
                        $targetDependencies       = explode(
                            ',',
                            str_replace(
                                ' ',
                                '',
                                $targetDependenciesString
                            )
                        );
                        $callbackTargets = array_merge(
                            $callbackTargets,
                            $targetDependencies
                        );
                        $targetArray += array(
                             'dependencies' => $targetDependencies,
                             'type'         => 'build',
                        );
                    if (count($targetDependencies) > 1) {
                        $targetArray['type'] = 'build';
                        $buildTargets[]   = $targetName;
                    }
                }

                if (count($target->xpath('./replacedby')) == 1) {
                          $replacedBy = (string) $target->xpath(
                              './replacedby[1]/@target'
                          )[0];
                          $deprecatedTargets[] = $targetName;
                          $targetArray         = array_merge(
                              $targetArray,
                              array(
                                  'type'        => 'deprecated',
                                  'description' => $replacedBy,
                              )
                          );
                }

                $callbackTargets = array_unique($callbackTargets);
                $targetsArray[]  = $targetArray;
            }//end foreach
        }//end foreach

        foreach ($targetsArray as $key => $targetArray) {
            if (in_array($targetArray['name'], $callbackTargets) && !in_array(
                $targetArray['name'],
                $buildTargets
            )
            ) {
                $targetsArray[$key]['type'] = 'callback';
            } elseif (!isset($targetArray['type'])) {
                $targetsArray[$key]['type'] = 'helper';
            }
        }

        $targetTable = $this->wrapperTargetTable(
            $wrapperTargets,
            $buildTargets,
            $callbackTargets
        );

        file_put_contents(
          $project->getProperty('build.dir') . "/docs/target-list.md",
          $targetTable
        );

        foreach ($buildList as $buildFile => $info) {
            $depth = ($info['level'] + 1);
            $projectName = $info['name'];
            if (is_file($buildFile) && $info['name'] != "deprecated") {
                $xml     = simplexml_load_file($buildFile);
                $targets = array_filter(
                    $targetsArray,
                    function ($v, $k) use ($buildFile) {
                        return $v['buildfile'] === $buildFile;
                    },
                    ARRAY_FILTER_USE_BOTH
                );
                if (!empty($projectName) && count($targets) > 1) {
                    $output .= str_repeat('#', $depth).' '.$projectName."\n";
                    $output .= "<table>\n";
                        $output .= "    <thead>\n";
                        $output .= "        <tr align=\"left\">\n";
                        $output .= "            <th nowrap>Target type</th>\n";
                        $output .= "            <th nowrap>Name</th>\n";
                        $output .= "            <th nowrap>Description</th>\n";
                        $output .= "        </tr>\n";
                        $output .= "    </thead>\n";
                        $output .= "    <tbody>\n";
                    foreach ($targets as $targetName => $target) {
                        $output .= "        <tr>\n";
                        $output .= "            <td nowrap>\n";
                        if ($target['visibility'] === 'visible') {
                            $output .= "                <img src=\"https://cdn0.iconfinder.com/data/icons/octicons/1024/eye-16.png\" align=\"left\" alt=\"visible\" />\n";
                        } else {
                              $output .= "                <img src=\"https://cdn0.iconfinder.com/data/icons/octicons/1024/gist-secret-20.png\" align=\"left\" alt=\"hidden\" />\n";
                        }

                            switch ($target['type']) {
                        case 'wrapper':
                            $output .= "                <img src=\"https://cdn0.iconfinder.com/data/icons/octicons/1024/star-20.png\" align=\"left\" alt=\"wrapper\" />\n";
                            break;
                        case 'build':
                            $output .= "                <img src=\"https://cdn0.iconfinder.com/data/icons/octicons/1024/three-bars-20.png\" align=\"left\" alt=\"build\" />\n";
                            break;
                        case 'deprecated':
                            $output .= "                <img src=\"https://cdn0.iconfinder.com/data/icons/octicons/1024/trashcan-20.png\" align=\"left\" alt=\"deprecated\" />\n";
                            break;
                        case 'helper':
                            $output .= "                <img src=\"https://cdn0.iconfinder.com/data/icons/octicons/1024/tools-16.png\" align=\"left\" alt=\"helper\" />\n";
                            break;
                        case 'callback':
                            $output .= "                <img src=\"https://cdn0.iconfinder.com/data/icons/octicons/1024/zap-20.png\" align=\"left\" alt=\"callback\" />\n";
                            break;
                            }

                            $output .= "            </td>\n";
                            $output .= "            <td nowrap>".$target['name']."</td>\n";
                            $output .= "            <td width=\"80%\">".$target['description']."</td>\n";
                            $output .= "        </tr>\n";
                    }//end foreach

                        $output .= "    </tbody>\n";
                        $output .= "</table>\n\n";
                }//end if
            }//end if
        }//end foreach

        echo $output;

    }//end main()


    /**
     * This function should do something interesting.
     *
     * @param mixed $wrapperTargets  I have no idea.
     * @param array $buildTargets I have no idea.
     * @param array $callbackTargets something where
     *
     * @return void
     */
    protected function wrapperTargetTable($wrapperTargets, $buildTargets, $callbackTargets)
    {
        $output = '';
        foreach ($wrapperTargets as $targetName => $wrapperTarget) {
            $output .= "### ".$targetName."\n";
            $output .= "<table>\n";
            $output .= "    <thead>\n";
            $output .= "        <tr align=\"left\">\n";
            $output .= "            <th>Description</th>\n";
            $output .= "            <th width=\"100%\">".$wrapperTarget['description']."<img src=\"https://cdn0.iconfinder.com/data/icons/octicons/1024/checklist-20.png\" align=\"right\" /></th>\n";
            $output .= "        </tr>\n";
            $output .= "    </thead>\n";
            $output .= "    <tbody>\n";
            $output .= "        <tr>\n";
            $output .= "            <td colspan=\"2\">\n";
            $output .= "                <details><summary>Properties</summary>\n";
            $output .= "                <table width=\"100%\">\n";
            $output .= "                    <thead>\n";
            $output .= "                        <tr align=\"left\">\n";
            $output .= "                            <th nowrap>Property</th>\n";
            $output .= "                            <th nowrap>Value</th>\n";
            $output .= "                            <th width='\100%\"'>Description</th>\n";
            $output .= "                        </tr>\n";
            $output .= "                    </thead>\n";
            $output .= "                    <tbody>\n";
            foreach ($wrapperTarget['properties'] as $property) {
                $output .= "                        <tr>\n";
                $output .= "                            <td nowrap>".$property['name']."</td>\n";
                $output .= "                            <td nowrap>".$property['value']."</td>\n";
                $output .= "                            <td>".$property['description']."</td>\n";
                $output .= "                        </tr>\n";
            }

            $output .= "                    </tbody>\n";
            $output .= "                </table>\n";
            $output .= "                </details>\n";
            $output .= "            </td>\n";
            $output .= "        </tr>\n";
            $output .= "        <tr>\n";
            $output .= "            <td colspan=\"2\">\n";
            $output .= "                <details><summary>Playbook</summary>\n";
            $output .= "                <table width=\"100%\">\n";
            $output .= "                    <thead>\n";
            $output .= "                        <tr align=\"left\">\n";
            $output .= "                            <th>Callback target</th>\n";
            $output .= "                            <th>Buildfile</th>\n";
            $output .= "                            <th width=\"100%\">Description</th>\n";
            $output .= "                        </tr>\n";
            $output .= "                    </thead>\n";
            $output .= "                    <tbody>\n";
            foreach ($buildTargets[$targetName.'-build']['dependencies'] as $callback) {
                $output .= "                        <tr>\n";
                $output .= "                            <td nowrap>".$callback."</td>\n";
                $output .= "                            <td nowrap>".str_replace('build/', './', $callbackTargets[$callback]['buildfile'])."</td>\n";
                $output .= "                            <td>".$callbackTargets[$callback]['description']."</td>\n";
                $output .= "                        </tr>\n";
            }

            $output .= "                    </tbody>\n";
            $output .= "                </table>\n";
            $output .= "                </details>\n";
            $output .= "            </td>\n";
            $output .= "        </tr>\n";
            $output .= "    </tbody>\n";
            $output .= "</table>\n\n";
        }//end foreach

        return $output;

    }//end wrapperTargetTable()

    /**
     * Checks if all properties required for generating the aliases file are
     * present.
     *
     * @throws \BuildException
     *   Thrown when a required property is not present.
     *
     * @return void
     */
    protected function checkRequirements()
    {
        $required_properties = array('phingDir');
        foreach ($required_properties as $required_property) {
            if (empty($this->$required_property)) {
                throw new \BuildException(
                    "Missing required property '$required_property'."
                );
            }
        }

    }//end checkRequirements()

    /**
     * Sets the Phing file to generate docs for.
     *
     * @param string $buildfile The Phing directory to generate docs for.
     *
     * @return void
     */
    public function setBuildFile($buildfile)
    {
        $this->_buildFile = $buildfile;
    }//end setBuildFile()


}//end class
