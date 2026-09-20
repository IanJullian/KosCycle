<?php
declare(strict_types=1);
class UserRepository {
 public function findById(int $id):?array{ $st=db()->prepare('SELECT id,full_name,username,whatsapp,email,role,status,created_at FROM users WHERE id=? LIMIT 1'); $st->execute([$id]); return $st->fetch()?:null; }
 public function dashboardCounts():array{return ['users'=>(int)db()->query('SELECT COUNT(*) FROM users')->fetchColumn(),'customers'=>(int)db()->query("SELECT COUNT(*) FROM users WHERE role='customer'")->fetchColumn(),'sellers'=>(int)db()->query("SELECT COUNT(*) FROM users WHERE role='seller'")->fetchColumn(),'admins'=>(int)db()->query("SELECT COUNT(*) FROM users WHERE role='admin'")->fetchColumn()];}
 public function all():array{return db()->query('SELECT id,full_name,username,whatsapp,email,role,status,created_at FROM users ORDER BY id DESC')->fetchAll();}
 public function activeSellers():array{return db()->query("SELECT id,full_name,username FROM users WHERE role='seller' AND status='active' ORDER BY full_name")->fetchAll();}
 public function updateProfile(int $id,array $data):void{$st=db()->prepare('UPDATE users SET full_name=?,username=?,whatsapp=?,email=? WHERE id=?');$st->execute([$data['full_name'],$data['username'],$data['whatsapp'],$data['email'],$id]);}
 public function updateAdmin(int $id,array $data):void{
   $fields=['full_name','username','whatsapp','email','role','status']; $values=[]; $sets=[];
   foreach($fields as $field){$sets[]=$field.'=?';$values[]=$data[$field];}
   if(!empty($data['password'])){$sets[]='password_hash=?';$values[]=password_hash($data['password'],PASSWORD_DEFAULT);}
   $values[]=$id; $st=db()->prepare('UPDATE users SET '.implode(',',$sets).' WHERE id=?'); $st->execute($values);
 }
 public function existsIdentity(string $username,string $email,?int $exceptId=null):bool{ $sql='SELECT 1 FROM users WHERE (username=? OR email=?)'; $params=[$username,$email]; if($exceptId!==null){$sql.=' AND id<>?';$params[]=$exceptId;} $sql.=' LIMIT 1'; $st=db()->prepare($sql);$st->execute($params);return(bool)$st->fetchColumn();}
 public function createByAdmin(array $data):void{$st=db()->prepare('INSERT INTO users(full_name,username,whatsapp,email,password_hash,role,status) VALUES(?,?,?,?,?,?,?)');$st->execute([$data['full_name'],$data['username'],$data['whatsapp'],$data['email'],password_hash($data['password'],PASSWORD_DEFAULT),$data['role'],$data['status']]);}
}
