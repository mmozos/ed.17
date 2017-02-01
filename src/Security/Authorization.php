<?php

namespace App\Security;

use App\Security;

/**
 * Class Authorization.
 */
class Authorization extends Security\Connection\Connection
{
    /** @var string $current */
    protected static $current = 'dist';

    /**
     * @param string $current
     *
     * @return array
     *
     * @throws \NoticeException
     */
    public static function setCurrent($current)
    {
        static::$current = $current;
    }

    /**
     * @param array  $args
     * @param string $table
     *
     * @return array
     *
     * @throws \NoticeException
     */
    public function checkCredentials(array $args, $table = USER_TABLE)
    {
        return call_user_func(__METHOD__.static::$current, $args, $table);
    }

    /**
     * @param array  $args
     * @param string $table
     *
     * @return array
     *
     * @throws \NoticeException
     */
    private function checkCredentialsDist(array $args, $table)
    {   //$fields = static::parseFields($args, static::getFilename($slug), $table);
        /** @var array $credentials */
        $credentials = [
            'course' => $args['course'],
            'studentCode' => $args['studentCode'],
            'studentDay' => $args['studentDay'],
            'studentMonth' => $args['studentMonth'],
        ];
        /** @var \Doctrine\DBAL\Query\QueryBuilder $queryBuilder */
        $queryBuilder = $this->getQueryBuilder()
            ->select('u.*')->from($table, 'u')
            ->where('u.id_periodo = :course')
            ->andWhere('u.libro_escolaridad = :studentCode')
            ->andWhere('u.fec_dia = :studentDay')
            ->andWhere('u.fec_mes = :studentMonth')
            ->setParameters($credentials);

        /** @var \Doctrine\DBAL\Driver\Statement $query */
        $query = $queryBuilder->execute();

        /** @var array $user */
        $user = $query->fetch();

        if (!empty($user)) {
            /** @var array $codes */
            $codes = \def::dbSecurity();
            /** @var string $codPrueba */
            $codPrueba = $args['studentPassword'];
            if (isset($codes[$codPrueba])) {
                return array_merge(static::requestAccess($user, $codes[$codPrueba]), $credentials);
            }
            throw new \NoticeException(sprintf('The code you have entered does not match: \'%s\'', $codPrueba));
        }
        throw new \NoticeException(static::$debug ? sprintf('No results found for query: %s, with the following parameter values: [%s]', $queryBuilder->getSQL(), implode(', ', $queryBuilder->getParameters())) : 'No results found');
    }

    /**
     * @param array  $args
     * @param string $table
     *
     * @return array
     *
     * @throws \NoticeException
     */
    private function checkCredentialsLocal(array $args, $table)
    {
        //return $this->checkCredentialsDist($args, $table);
        $data = $this->getConnection()->getSchemaManager()->listTableDetails('ikasleak');
        //var_dump($data);exit;
        var_dump($this->getConnection()->fetchAll('select * from ikasleak'));
        $queryBuilder = $this->getQueryBuilder()
            ->select('u.*')->from($table, 'u');
        /** @var \Doctrine\DBAL\Driver\Statement $query */
        $query = $queryBuilder->execute();

        /** @var array $user */
        $user = $query->fetch();
        var_dump($user);

        /** @see http://stackoverflow.com/a/25211533 */
        /** @var \Doctrine\DBAL\Schema\MySqlSchemaManager $sm */
        $sm = $this->getConnection()->getSchemaManager();
        var_dump($sm->listTableDetails('ikasleak'));
        /** @var \Doctrine\DBAL\Query\QueryBuilder $queryBuilder */
        $queryBuilder = $this->getQueryBuilder();
    }

    /**
     * @param array        $user
     * @param array|string $args
     *
     * @return array
     */
    private static function requestAccess(array $user, $args)
    {
        $data = static::getAccess($user, $args);
        foreach (\def::stages() as $stage) {
            if (strpos($data['table'], $stage) !== false/* && empty($data['stage'])*/) {
                $data['stage'] = $stage;
                break;
            }
        }

        return $data;
    }

    /**
     * @param array        $user
     * @param array|string $args
     *
     * @return array
     *
     * @throws \NoticeException
     */
    private static function getAccess(array $user, $args)
    {
        if (is_array($args)) {
            foreach ($args as $key => $item) {
                if (/* Eusk: */(strpos($item, 'eus') !== false &&
                        (strtolower($key) === strtolower($user['tipo_eus']) || strpos(strtolower($key), lcfirst($user['codmodelo'])) !== false) &&
                        $mod = 'eus') ||
                    /* Gazte: */
                    (strpos($item, 'cas') !== false /*&& lcfirst($key) === lcfirst($user['tipo_cas'])*/ && $mod = 'cas') ||
                    /* G. sortak: */
                    (strpos($item, 'gsorta') !== false && lcfirst($key) === lcfirst($user['tipo_gso']) && $mod = 'gso') ||
                    /* Inge: */
                    (strpos($item, 'ing') !== false /*&& lcfirst($key) === lcfirst($user['tipo_ing'])*/ && $mod = 'ing') ||
                    /* Mate: */
                    (strpos($item, 'mat') !== false /*&& lcfirst($key) === lcfirst($user['tipo_mat'])*/ && $mod = 'mat') ||
                    /* Zie: */
                    (strpos($item, 'zie') !== false /*&& lcfirst($key) === lcfirst($user['tipo_zie'])*/ && $mod = 'zie')) {
                    return ['lengua' => $lengua = static::getLanguage($user, $mod), 'lang' => \def::langCodes()[$lengua], 'table' => $item];
                }
            }
        } elseif (/* Eusk: */(strpos($args, 'eus') !== false && $mod = 'eus') ||
            /* Gazte: */
            ((strpos($args, 'cas') !== false || strpos($args, 'gaz') !== false) && $mod = 'cas') ||
            /* G. sortak: */
            (strpos($args, 'gsorta') !== false && $mod = 'gso') ||
            /* Inge: */
            (strpos($args, 'ing') !== false && $mod = 'ing') ||
            /* Mate: */
            (strpos($args, 'mat') !== false && $mod = 'mat') ||
            /* Zie: */
            (strpos($args, 'zie') !== false && $mod = 'zie')) {
            return ['lengua' => $lengua = static::getLanguage($user, $mod), 'lang' => \def::langCodes()[$lengua], 'table' => $args];
        } else {
            throw new \NoticeException(sprintf('Access denied for student \'%s\'', $user['libro_escolaridad']));
        }
    }

    /**
     * @param array  $user
     * @param string $default
     * @param array  $asIs
     *
     * @return null|string
     *
     * @throws \NoticeException
     */
    private static function getLanguage($user, $default, $asIs = ['eus', 'cas'])
    {
        if ($user['lengua_tipo'] === 'fam') {
            return $user['lengua'];
        }
        if ($user['lengua_tipo'] === 'ins' && isset($user['lengua_'.$default])) {
            return $user['lengua_'.$default];
        }
        if (in_array($default, $asIs)) {
            return $default;
        }
        throw new \NoticeException(sprintf('No language found for student \'%s\'', $user['libro_escolaridad']));
    }
}
