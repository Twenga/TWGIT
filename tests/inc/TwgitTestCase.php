<?php

use PHPUnit\Framework\TestCase;

/**
 * Classe parente des tests Twgit, permet de faciliter les interactions entre PHP, Shell et Git.
 *
 * @package Tests
 * @author Geoffroy Aubry <geoffroy.aubry@hi-media.com>
 * @author Geoffroy Letournel <gletournel@hi-media.com>
 */
class TwgitTestCase extends TestCase
{
    /**
     * The name of the Git "stable" branch
     */
    const STABLE = TWGIT_STABLE;

    /**
     * The shortname of the Git remote
     */
    const ORIGIN = TWGIT_ORIGIN;

    /**
     * @var string The name of the remote "stable" branch
     */
    protected static $_remoteStable;

    /**
     * Répertoire des dépôt locaux.
     * @var array
     */
    private static $_aLocalRepositoriesDir = array(
        1 => TWGIT_REPOSITORY_LOCAL_DIR,
        2 => TWGIT_REPOSITORY_SECOND_LOCAL_DIR
    );

    /**
     * @var Shell_Adapter
     */
    protected static $_oShell = NULL;

    /**
     * @var string
     * @see setUp();
     */
    private static $_sSetUpCmd = '';

    /**
     * Singleton.
     *
     * @return Shell_Adapter
     */
    protected static function _getShellInstance ()
    {
        if (self::$_oShell === NULL) {
            self::$_oShell = new Shell_Adapter();
        }
        return self::$_oShell;
    }

    /**
     * Exécute la commande shell spécifiée et retourne la sortie découpée par ligne dans un tableau.
     * En cas d'erreur shell (code d'erreur <> 0), lance une exception incluant le message d'erreur.
     *
     * @param string $sCmd
     * @return array tableau indexé du flux de sortie shell découpé par ligne
     * @throws RuntimeException en cas d'erreur shell
     */
    protected static function _rawExec ($sCmd)
    {
        return self::_getShellInstance()->exec($sCmd);
    }

    /**
     * Constructs a test case with the given name.
     *
     * @param  string $name
     * @param  array  $data
     * @param  string $dataName
     */
    public function __construct($sName=NULL, array $aData=array(), $sDataName='')
    {
        parent::__construct($sName, $aData, $sDataName);
        self::$_remoteStable = self::ORIGIN . '/' . self::STABLE;
    }

    /**
     * Sets up the fixture, for example, open a network connection.
     * This method is called before a test is executed.
     */
    public function setUp ()
    {
        if (empty(self::$_sSetUpCmd)) {
            $aDir = array(
                TWGIT_REPOSITORY_ORIGIN_DIR,
                TWGIT_REPOSITORY_LOCAL_DIR,
                TWGIT_REPOSITORY_SECOND_LOCAL_DIR,
                TWGIT_REPOSITORY_SECOND_REMOTE_DIR,
                TWGIT_REPOSITORY_THIRD_REMOTE_DIR
            );
            $aCmd = array();
            foreach ($aDir as $sDir) {
                if (strpos($sDir, TWGIT_TMP_DIR . '/') !== 0) {
                    throw new RuntimeException("Security check before 'rm -rf'…");
                }
                $aCmd[] = "rm -rf '$sDir' && mkdir -p '$sDir' && chmod 0777 '$sDir'";
            }
            self::$_sSetUpCmd = implode(' && ', $aCmd);
        }

        $this->_rawExec(self::$_sSetUpCmd);
        copy(TWGIT_TMP_DIR . '/conf-twgit.sh', TWGIT_REPOSITORY_LOCAL_DIR . '/.twgit');
        copy(TWGIT_TMP_DIR . '/conf-twgit.sh', TWGIT_REPOSITORY_SECOND_LOCAL_DIR . '/.twgit');
    }

    /**
     * Get the name of a remote branch.
     *
     * @param string $name   The branch name (e.g. master, stable, hotfix-42)
     * @param string $remote The name of the Remote (e.g. origin)
     *
     * @return string Returns the name of the remote branch
     */
    protected static function _remote($name, $remote = null)
    {
        if ($remote === null) {
            $remote = TWGIT_ORIGIN;
        }

        return $remote . '/' . $name;
    }

    /**
     * Get a list of remote branches.
     *
     * @param array  $branches The branches names (e.g. master, issues, feature-1)
     * @param string $remote   The name of the Remote (e.g. origin)
     *
     * @return array Returns a list of remote branches
     */
    protected static function _remotes(array $branches, $remote = null)
    {
        if ($remote === null) {
            $remote = TWGIT_ORIGIN;
        }

        $result = array();

        foreach ($branches as $branch) {
            $result[] = 'remotes/' . $remote . '/' . $branch;
        }

        return $result;
    }

    /**
     * Supprime les couleurs Shell du message spécifié.
     *
     * @param string $sMsg
     * @return string
     */
    protected static function stripColors ($sMsg)
    {
        return preg_replace('/\x1B\[([0-9]{1,2}(;[0-9]{1,2})?)?[m|K]/', '', $sMsg);
    }

    /**
     * Exécute la commande shell spécifiée et retourne la sortie d'exécution sous forme d'une chaîne de caractères.
     * L'éventuelle coloration Shell est enlevée.
     * En cas d'erreur shell (code d'erreur <> 0), lance une exception incluant le message d'erreur.
     *
     * @param string $sCmd
     * @param bool $bStripBashColors Supprime ou non la coloration Bash de la chaîne retournée
     * @return string sortie d'exécution sous forme d'une chaîne de caractères.
     * @throws RuntimeException en cas d'erreur shell
     */
    protected function _exec ($sCmd, $bStripBashColors=true)
    {
        try {
            $aResult = self::_rawExec($sCmd);
        } catch (RuntimeException $oException) {
            $sMsg = ($oException->getMessage() != '' ? $oException->getMessage() : '-- no message --');
            throw new RuntimeException(
                self::stripColors($sMsg),
                $oException->getCode(),
                $oException
            );
        }
        $sMsg = implode("\n", $aResult);
        if ($bStripBashColors) {
            $sMsg = preg_replace('/\x1B\[([0-9]{1,2}(;[0-9]{1,2})?)?[m|K]/', '', $sMsg);
        } else {
            $sMsg = str_replace("\033", '\033', $sMsg);
        }
        return $sMsg;
    }

    /**
     * Exécute la commande shell spécifiée dans le répertoire du dépôt Git local,
     * et retourne la sortie d'exécution sous forme d'une chaîne de caractères.
     * L'éventuelle coloration Shell est enlevée.
     *
     * En cas d'erreur shell (code d'erreur <> 0), lance une exception incluant le message d'erreur.
     *
     * @param string $sCmd
     * @param bool $bStripBashColors Supprime ou non la coloration Bash de la chaîne retournée
     * @param int $iWhichLocalDir Spécifie le dépôt local concerné
     * @return string sortie d'exécution sous forme d'une chaîne de caractères.
     * @throws RuntimeException en cas d'erreur shell
     * @see TwgitTestCase::$_aLocalRepositoriesDir
     */
    protected function _localExec ($sCmd, $bStripBashColors=true, $iWhichLocalDir=1)
    {
        $sLocalCmd = 'cd ' . self::$_aLocalRepositoriesDir[$iWhichLocalDir] . ' && ' . $sCmd;
        return $this->_exec($sLocalCmd, $bStripBashColors);
    }

    /**
     * Appelle une fonction de inc/common.inc.sh une fois dans le répertoire du dépôt Git local,
     * et retourne la sortie d'exécution sous forme d'une chaîne de caractères.
     * L'éventuelle coloration Shell est enlevée.
     * Les fichiers de configuration Shell sont préalablement chargés.
     *
     * En cas d'erreur shell (code d'erreur <> 0), lance une exception incluant le message d'erreur.
     *
     * Par exemple : $this->_localFunctionCall('process_fetch x');
     *
     * @param string $sCmd
     * @param bool $bStripBashColors Supprime ou non la coloration Bash de la chaîne retournée
     * @param int $iWhichLocalDir Spécifie le dépôt local concerné
     * @return string sortie d'exécution sous forme d'une chaîne de caractères.
     * @throws RuntimeException en cas d'erreur shell
     * @see TwgitTestCase::$_aLocalRepositoriesDir
     */
    protected function _localFunctionCall ($sCmd, $bStripBashColors=true, $iWhichLocalDir=1)
    {
        $sFunctionCall = TWGIT_BASH_EXEC . ' ' . TWGIT_TESTS_INC_DIR . '/testFunction.sh ' . $sCmd;
        return $this->_localExec($sFunctionCall, $bStripBashColors, $iWhichLocalDir);
    }

    /**
     * Exécute du code appelant des fonctions de inc/common.inc.sh une fois dans le répertoire du dépôt Git local,
     * et retourne la sortie d'exécution sous forme d'une chaîne de caractères.
     * L'éventuelle coloration Shell est enlevée.
     * Les fichiers de configuration Shell sont préalablement chargés.
     *
     * En cas d'erreur shell (code d'erreur <> 0), lance une exception incluant le message d'erreur.
     *
     * Par exemple : $this->_localShellCodeCall('process_options x -aV; isset_option a; echo \$?');
     * Attention à l'échappement des dollars ($).
     *
     * @param string $sCmd
     * @param bool $bStripBashColors Supprime ou non la coloration Bash de la chaîne retournée
     * @param int $iWhichLocalDir Spécifie le dépôt local concerné
     * @return string sortie d'exécution sous forme d'une chaîne de caractères.
     * @throws RuntimeException en cas d'erreur shell
     * @see TwgitTestCase::$_aLocalRepositoriesDir
     */
    protected function _localShellCodeCall ($sCmd, $bStripBashColors=true, $iWhichLocalDir=1)
    {
        $sShellCodeCall = TWGIT_BASH_EXEC . ' ' . TWGIT_TESTS_INC_DIR . '/testShellCode.sh "' . $sCmd . '"';
        return $this->_localExec($sShellCodeCall, $bStripBashColors, $iWhichLocalDir);
    }

    /**
     * Exécute la commande shell spécifiée dans le répertoire du dépôt Git distant,
     * et retourne la sortie d'exécution sous forme d'une chaîne de caractères.
     * L'éventuelle coloration Shell est enlevée.
     * En cas d'erreur shell (code d'erreur <> 0), lance une exception incluant le message d'erreur.
     *
     * @param string $sCmd
     * @param bool $bStripBashColors Supprime ou non la coloration Bash de la chaîne retournée
     * @return string sortie d'exécution sous forme d'une chaîne de caractères.
     * @throws RuntimeException en cas d'erreur shell
     */
    protected function _remoteExec ($sCmd, $bStripBashColors=true)
    {
        $sRemoteCmd = 'cd ' . TWGIT_REPOSITORY_ORIGIN_DIR . ' && ' . $sCmd;
        return $this->_exec($sRemoteCmd, $bStripBashColors);
    }
}
