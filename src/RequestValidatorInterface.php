<?php
/**
 * Created by PhpStorm.
 * User: atedeschi
 * Date: 30/03/16
 * Time: 12.02
 */
namespace Webgriffe\LibUnicreditImprese;

interface RequestValidatorInterface
{
    /**
     * @param array $data
     * @return mixed
     */
    public function validate(array $data);
}
