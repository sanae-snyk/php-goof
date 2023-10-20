<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

use App\Models\ChozaiShukei;

class SampleController extends Controller {
  private $colNames = [
    'date' => '日付',
    'yobi' => '曜日',
    'mp_nam' => '店舗',
    'bumonCode' => '部門コード',
    'gp_nam' => 'グループ',
    'groupBumonCode' => 'ｸﾞﾙｰﾌﾟ部門ｺｰﾄﾞ',
    'uketsukeKensu' => '受付件数',
    'souTensu' => '総点数',
    'gijutsuRyo' => '技術料',
    'yakuzaiRyo' => '薬剤料',
    'kanjaHutanKin' => '患者負担金額',
    'hokenSeikyuKin' => '保険請求金額',
    'kaigoKensu' => '介護件数',
    'kaigoTensu' => '介護点数',
    'kaigoHutanKin' => '介護負担金額',
    'kaigoSeikyuKin' => '介護請求金額',
    'mp_cod' => '店舗コード（隠しフィールド）'
  ];

  private $hiddenList = ['mp_cod'];

  private $groupingList = ['date', 'mp_nam', 'yobi', 'gp_nam'];

  public function output(Request $request)
  {
    $allList = array_keys($this->colNames);
    $diffList = array_diff($allList, $this->hiddenList);

    // inputパラメータの受け取り
    $fileType = ($request->get('fileType'))? $request->get('fileType'):config('const.FILE_TYPE_JSON');
    $dateType = (($request->get('dateType') >= 0)? $request->get('dateType'): 1);  // 0：月, 1：週, 2：日
    $dateCase = (($request->get('dateCase') >= 0)? $request->get('dateCase'): 1);  // 0：今, 1：前, 2：指定
    $dayStart = ($request->get('dayStart'))? $request->get('dayStart'):'';
    $dayEnd = ($request->get('dayEnd'))? $request->get('dayEnd'):'';
    $shopType = ($request->get('shopType'))? $request->get('shopType'): (($this->isHonbuUser(Auth::user()->shopId))? 0: 2);
    $group = ($request->get('group'))? $request->get('group'):0;
    $shop = ($request->get('shop'))? $request->get('shop'): (($this->isHonbuUser(Auth::user()->shopId))? '': Auth::user()->shopId);
    $gp_cod = ($request->get('gp_cod')? $request->get('gp_cod'): 0);
    $sortId = ($request->get('sortId'))? $request->get('sortId'):json_encode($diffList);
    $checkId = ($request->get('checkId'))? $request->get('checkId'):json_encode($diffList);

    $disp = $request->get('disp')? $request->get('disp'): 0;
    $dateUnit = $request->get('dateUnit')? $request->get('dateUnit'): 0;

    $this->SetDateRange($dateType, $dateCase, $dayStart, $dayEnd);
    $chozaiShukei = new ChozaiShukei;
    $result = $chozaiShukei->search($shopType, $group, $shop, $dayStart, $dayEnd, '', $dateUnit, $disp, $this->groupingList, json_decode($sortId)[0], $gp_cod);

    $this->outputResult(
      $fileType,
      $result,
      $checkId,
      (($request->get('colModel'))? $request->get('colModel'): ''),
      (($request->get('searchRules'))? $request->get('searchRules'): ''),
      []
    );
  }

  /**　
   * 本部ユーザー？
   *
   * @param string $userShopId
   * @return bool
   */
  public static function isHonbuUser ($userShopId) {
    return ($userShopId == '9999999');
  }

  /**　
   * 集計日付範囲を設定
   *
   * @param string $dateType
   * @param string $dateCase
   * @param string &$dayStart
   * @param string &$dayEnd
   * @return void
   */
  private function SetDateRange($dateType, $dateCase, &$dayStart, &$dayEnd) {
    $ds = '';  // 集計開始日付
    $de = '';  // 集計終了日付
    switch($dateType) {
    // 月
    case 0:
      switch($dateCase) {
        case 0:  // 今月
          $ds = date('Y/m/d', strtotime('first day of'));
          break;
        case 1:  // 前月
          $ds = date('Y/m/d', strtotime('first day of -1 month'));
          break;
        case 2:  // 指定
          $ds = ($dayStart)? date('Y/m/d', strtotime('first day of ' . str_replace('/', '-', $dayStart))): '';
          break;
      }
      $de = ($ds)? date('Y/m/d', strtotime('last day of ' . $ds)): '';
      break;
    // 週
    case 1:
      switch($dateCase) {
        case 0:  // 今週
          $ds = date('Y/m/d');
          break;
        case 1:  // 先週
          $ds = date('Y/m/d', strtotime($ds.'-1 week'));
          break;
        case 2:  // 指定
          $ds = date('Y/m/d', strtotime('first day of ' . str_replace('/', '-', $dayStart)));
          if (date('w', strtotime($ds))) { $ds = date('Y/m/d', strtotime($ds . '+1 week')); }
          $ds = ($dayStart)? date('Y/m/d', strtotime($ds . '+' . (($dayEnd)? $dayEnd: 0)  . 'week')): '';
          break;
        }
        $ds = ($ds)? date('Y/m/d', strtotime($ds . '-' . date('w', strtotime($ds)) . 'day')): '';
        $de = ($ds)? date('Y/m/d', strtotime($ds . '+6 day')): '';
        break;
    // 日
    case 2:
      switch($dateCase) {
        case 0:  // 今日
          $ds = date('Y/m/d');
          $de = date('Y/m/d');
          break;
        case 1:  // 前日
          $ds = date('Y/m/d', strtotime('-1 day'));
          $de = date('Y/m/d', strtotime('-1 day'));
          break;
        case 2:  // 指定
          $ds = $dayStart;
          $de = $dayEnd;
          break;
      }
      break;
    }
    $dayStart = $ds;
    $dayEnd = $de;
  }

  protected function outputResult($fileType, $result, $checkId, $colModel = '', $searchRules = '', $total = [])
  {
    // 出力
    switch ($fileType) {
      case config('const.FILE_TYPE_JSON'):
        $json = json_encode($result);
        print $json;
        break;
      
      default:
        # code...
        break;
    }
  }
}
