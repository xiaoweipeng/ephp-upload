<?php
class Upload{
  private $filepath = './upload'; //上传目录
  private $tmpPath; //PHP文件临时目录
  private $blobNum; //第几个文件块
  private $totalBlobNum; //文件块总数
  private $fileName; //文件名
  private $hashCode; //文件名
  private $SecondUpload = ['state'=>'error','data'=>[]];

  public function __construct($tmpPath,$blobNum,$totalBlobNum,$fileName,$hashCode=""){
  
    $this->hashCode = $hashCode;
    $this->tmpPath = $tmpPath;
    $this->blobNum = $blobNum;
    $this->totalBlobNum = $totalBlobNum;
    $this->fileName = $fileName;
    
    if(!empty($hashCode) && $blobNum==1)
    {
        //....redis换成了ini配置,有条件的同学自己换成redis
        if(file_exists("./hashFile.ini")){
            $hash = parse_ini_file("./hashFile.ini",true);
            if(isset($hash[$hashCode]['file'])){ //如果存在就返回 不存在就继续上传
                $this->SecondUpload=['state'=>'ok','data'=>[ 'code'=>3,
                'msg'=>'success',
                'file_path'=>$hash[$hashCode]['file']
            ]];
                
                return;
            }
        }
    }
     
    $this->moveFile();
    //$this->fileMerge();
  }
   
  //判断是否是最后一块，如果是则进行文件合成并且删除文件块
  private function fileMerge(){

    if($this->blobNum == $this->totalBlobNum){
      $blob = '';
      for($i=1; $i<= $this->totalBlobNum; $i++){
        $blob .= file_get_contents($this->filepath.'/'. $this->fileName.'__'.$i);
      }
      file_put_contents($this->filepath.'/'. $this->fileName,$blob);
      $this->deleteFileBlob();
    }
  }
   
  //删除文件块
  private function deleteFileBlob(){
    for($i=1; $i<= $this->totalBlobNum; $i++){
      @unlink($this->filepath.'/'. $this->fileName.'__'.$i);
    }
  }
   
  //移动文件
  private function moveFile(){
    $this->touchDir();
    $filename = $this->filepath.'/'. $this->fileName.'__'.$this->blobNum;
    move_uploaded_file($this->tmpPath,$filename);
    $handle = fopen($this->filepath.'/'.$this->fileName,"a");
    if($handle!=false){
        fwrite($handle,file_get_contents($filename));
        fclose($handle);
        unlink($filename);
    }
  }
   
  //API返回数据
  public function apiReturn(){
    if($this->blobNum == $this->totalBlobNum){
        if(file_exists($this->filepath.'/'. $this->fileName)){   
          $data['code'] = 2;
          $data['msg'] = 'success';
          $data['file_path'] = $this->fileName;
          if(!empty($this->hashCode))
            $this->write_ini_file([$this->hashCode=>['file'=>$this->fileName]]);
        }
    }else if($this->SecondUpload['state']!="ok"){
        
          $data['code'] = 1;
          $data['msg'] = 'waiting for all';
          $data['file_path'] = '';
        
    }else{
      $data = $this->SecondUpload['data'];
    }
    header('Content-type: application/json');
    echo json_encode($data,256);
  }
   
  //建立上传文件夹
  private function touchDir(){
    if(!file_exists($this->filepath)){
      return mkdir($this->filepath);
    }
  }
  function write_ini_file($assoc_arr, $path="./hashFile.ini", $has_sections=true)  
{
    $data = [];
    if(file_exists($path))
        $data = parse_ini_file($path,true);   
     
    $assoc_arr=array_merge($assoc_arr,$data);
    $content = "";  
    if ($has_sections)  
    {  
        foreach ($assoc_arr as $key=>$elem)  
        {  
            $content .= "[".$key."]\n";  
            foreach ($elem as $key2=>$elem2)  
            {  
                if(is_array($elem2))  
                {  
                    for($i=0;$i<count($elem2);$i++)  
                    {  
                        $content .= $key2."[] = \"".$elem2[$i]."\"\n";  
                    }  
                }  
                else if($elem2=="") $content .= $key2." = \n";  
                else $content .= $key2." = \"".$elem2."\"\n";  
            }  
        }  
    }  
    else  
    {  
        foreach ($assoc_arr as $key=>$elem)  
        {  
            if(is_array($elem))  
            {  
                for($i=0;$i<count($elem);$i++)  
                {  
                    $content .= $key2."[] = \"".$elem[$i]."\"\n";  
                }  
            }  
            else if($elem=="") $content .= $key2." = \n";  
            else $content .= $key2." = \"".$elem."\"\n";  
        }  
    }  
    if (!$handle = fopen($path, 'w'))  
    {  
        return false;  
    }  
    if (!fwrite($handle, $content))  
    {  
        return false;  
    }  
    fclose($handle);  
    return true;  
    } 
}
 
//实例化并获取系统变量传参
$upload = new Upload($_FILES['file']['tmp_name'],$_POST['blob_num'],$_POST['total_blob_num'],$_POST['file_name'],$_POST['hashCode']);
//调用方法，返回结果
$upload->apiReturn();