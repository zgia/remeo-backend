<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// 基于原库实际结构生成；已有表不重建，便于导入旧库后接管迁移。
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('category')) {
            Schema::create('category', function (Blueprint $table): void {
                $table->increments('id');
                $table->integer('parentid')->unsigned()->default('0');
                $table->string('ckey', 128)->default('');
                $table->string('title', 128)->default('');
            });
        }
        if (! Schema::hasTable('hospday')) {
            Schema::create('hospday', function (Blueprint $table): void {
                $table->increments('id');
                $table->integer('patientid')->unsigned()->default('0')->comment('患者');
                $table->tinyInteger('hospseq')->unsigned()->default('0')->comment('第几次住院');
                $table->date('inday')->nullable()->comment('住院日期');
            });
        }
        if (! Schema::hasTable('hospital')) {
            Schema::create('hospital', function (Blueprint $table): void {
                $table->increments('id');
                $table->string('title', 128)->default('')->comment('医院名称');
                $table->integer('createdat')->unsigned()->default('0');
                $table->integer('updatedat')->unsigned()->default('0');
                $table->integer('deletedat')->unsigned()->default('0');
            });
        }
        if (! Schema::hasTable('log')) {
            Schema::create('log', function (Blueprint $table): void {
                $table->increments('id')->comment('主键 日志ID');
                $table->string('type', 64)->default('')->comment('日志操作类型');
                $table->integer('objectid')->unsigned()->default('0')->comment('操作对象ID');
                $table->string('script', 128)->default('')->comment('脚本名称');
                $table->string('action', 32)->default('')->comment('执行操作');
                $table->string('ipaddress', 20)->default('')->comment('IP地址');
                $table->integer('userid')->unsigned()->default('0')->comment('外键 用户ID');
                $table->mediumText('fromcontent')->comment('修改前的内容');
                $table->mediumText('tocontent')->comment('修改后的内容');
                $table->integer('createdat')->unsigned()->default('0')->comment('操作时间');
                $table->index(['type'], 'log_objecttitle');
                $table->index(['objectid'], 'log_objectid');
            });
        }
        if (! Schema::hasTable('media')) {
            Schema::create('media', function (Blueprint $table): void {
                $table->increments('id');
                $table->integer('treatmentid')->unsigned()->default('0');
                $table->integer('patientid')->unsigned()->default('0');
                $table->string('file', 255)->default('')->comment('文件');
                $table->string('thumb', 255)->default('')->comment('缩略图');
                $table->string('mdesc', 255)->default('')->comment('描述');
                $table->tinyInteger('deleted')->unsigned()->default('0');
                $table->integer('createdat')->unsigned()->default('0');
                $table->integer('updatedat')->unsigned()->default('0');
                $table->integer('deletedat')->unsigned()->default('0');
                $table->index(['patientid'], 'media_idx_patient');
            });
        }
        if (! Schema::hasTable('pathology')) {
            Schema::create('pathology', function (Blueprint $table): void {
                $table->increments('id');
                $table->integer('patientid')->unsigned()->default('0');
                $table->tinyInteger('histopathology')->unsigned()->default('0')->comment('组织病理');
                $table->tinyInteger('who')->unsigned()->default('0')->comment('WHO分级');
                $table->tinyInteger('tertc228t')->unsigned()->default('0')->comment('tertc228t');
                $table->tinyInteger('tertc250t')->unsigned()->default('0')->comment('tertc250t');
                $table->tinyInteger('cdkn2a')->unsigned()->default('0')->comment('cdkn2a');
                $table->tinyInteger('cdkn2b')->unsigned()->default('0')->comment('cdkn2b');
                $table->tinyInteger('pten')->unsigned()->default('0')->comment('pten');
                $table->tinyInteger('smarcb1')->unsigned()->default('0')->comment('smarcb1');
                $table->tinyInteger('pik3ca')->unsigned()->default('0')->comment('pik3ca');
                $table->string('ki67', 7)->default('')->comment('Ki-67');
                $table->tinyInteger('h3k27m')->unsigned()->default('0')->comment('h3k27m');
                $table->text('pydesc')->nullable()->comment('描述');
                $table->date('pyday')->nullable()->comment('病理日期');
                $table->string('pycode', 32)->default('')->comment('病理号');
                $table->tinyInteger('hospseq')->unsigned()->default('0')->comment('住院次数');
                $table->integer('createdat')->unsigned()->default('0');
                $table->integer('updatedat')->unsigned()->default('0');
                $table->integer('deletedat')->unsigned()->default('0');
                $table->index(['patientid'], 'pathology_idx_patient');
            });
        }
        if (! Schema::hasTable('patient')) {
            Schema::create('patient', function (Blueprint $table): void {
                $table->increments('id');
                $table->integer('doctorid')->unsigned()->default('0')->comment('属于哪个医生');
                $table->integer('hospitalid')->unsigned()->default('0')->comment('属于哪个医院');
                $table->tinyInteger('age')->unsigned()->default('0')->comment('年龄');
                $table->string('idcard', 18)->default('')->comment('身份证号码');
                $table->string('realname', 128)->default('')->comment('姓名');
                $table->tinyInteger('gender')->unsigned()->default('0')->comment('性别，1：男，2：女');
                $table->string('mobile', 15)->default('')->comment('联系方式');
                $table->string('linkman', 128)->comment('联系人');
                $table->string('linkrel', 64)->comment('关系');
                $table->string('mobile2', 15)->default('')->comment('联系方式');
                $table->string('linkrel2', 64)->comment('关系');
                $table->string('linkman2', 128)->comment('联系人');
                $table->string('mobile3', 15)->default('')->comment('联系方式');
                $table->string('linkrel3', 64)->comment('关系');
                $table->string('linkman3', 128)->comment('联系人');
                $table->date('firstday')->nullable()->comment('首诊日期：2022-10-12');
                $table->date('nextcheckday')->nullable()->comment('下次复查日期');
                $table->date('lastcheckday')->nullable()->comment('末次复查日期');
                $table->integer('lastcheckid')->default('0')->comment('末次复查的treatment id');
                $table->smallInteger('checkcount')->unsigned()->default('0')->comment('复查次数');
                $table->date('nextfollowupday')->nullable()->comment('下次随访日期');
                $table->date('lastfollowupday')->nullable()->comment('末次随访日期');
                $table->integer('lastfollowupid')->default('0')->comment('末次随访的treatment id');
                $table->smallInteger('followupcount')->unsigned()->default('0')->comment('随访次数');
                $table->tinyInteger('isout')->unsigned()->default('0')->comment('是否门诊：1：是，0：否');
                $table->tinyInteger('deletedout')->unsigned()->default('0')->comment('是否门诊删除：1：是，0：否');
                $table->tinyInteger('isin')->unsigned()->default('0')->comment('是否住院：1：是，0：否');
                $table->tinyInteger('deletedin')->unsigned()->default('0')->comment('是否住院删除：1：是，0：否');
                $table->string('hospcode', 32)->default('')->comment('住院号');
                $table->date('hospday')->nullable()->comment('住院日期：2022-10-12');
                $table->tinyInteger('hospseq')->unsigned()->default('0')->comment('住院次数');
                $table->tinyInteger('surgeryseq')->unsigned()->default('0')->comment('手术在第几次住院时进行');
                $table->tinyInteger('hospinitialstate')->unsigned()->default('0')->comment('初始状态：1：初发，2：复发');
                $table->tinyInteger('isalive')->unsigned()->default('1')->comment('1：存活，2：死亡');
                $table->tinyInteger('recurrence')->unsigned()->default('1')->comment('1：未见复发2：（复发）复发/残余病灶增大3：残余病灶稳定4：残余病灶变小');
                $table->integer('createdat')->unsigned()->default('0');
                $table->integer('updatedat')->unsigned()->default('0');
                $table->integer('deletedat')->unsigned()->default('0');
                $table->index(['hospitalid', 'doctorid'], 'patient_idx_hospdoc');
            });
        }
        if (! Schema::hasTable('permission')) {
            Schema::create('permission', function (Blueprint $table): void {
                $table->increments('id')->comment('主键');
                $table->integer('groupid')->unsigned()->default('0')->comment('权限组');
                $table->string('code', 32)->default('')->comment('权限Code');
                $table->string('title', 128)->default('')->comment('权限名称');
                $table->integer('createdat')->unsigned()->default('0');
                $table->integer('updatedat')->unsigned()->default('0');
                $table->integer('deletedat')->unsigned()->default('0');
            });
        }
        if (! Schema::hasTable('progress')) {
            Schema::create('progress', function (Blueprint $table): void {
                $table->increments('id');
                $table->integer('patientid')->unsigned()->comment('病人');
                $table->tinyInteger('firstsituation')->unsigned()->default('0')->comment('门诊情况，1：做了，2：没做');
                $table->tinyInteger('firstvideo')->unsigned()->default('0')->comment('门诊影像，1：做了，2：没做');
                $table->tinyInteger('hospsituation')->unsigned()->default('0')->comment('入院情况，1：做了，2：没做');
                $table->tinyInteger('surgery')->unsigned()->default('0')->comment('手术，1：做了，2：没做');
                $table->tinyInteger('vbs')->unsigned()->default('0')->comment('术前影像，1：做了，2：没做');
                $table->tinyInteger('vas')->unsigned()->default('0')->comment('术后影像，1：做了，2：没做');
                $table->tinyInteger('pathology')->unsigned()->default('0')->comment('病理，1：做了，2：没做');
                $table->tinyInteger('discharge')->unsigned()->default('0')->comment('出院，1：做了，2：没做');
                $table->unique(['patientid'], 'progress_idx_patient');
            });
        }
        if (! Schema::hasTable('radiotherapy')) {
            Schema::create('radiotherapy', function (Blueprint $table): void {
                $table->increments('id');
                $table->integer('treatmentid')->unsigned()->default('0');
                $table->integer('patientid')->unsigned()->default('0');
                $table->tinyInteger('ryaction')->unsigned()->default('0')->comment('是否放疗，1：是，2：否');
                $table->smallInteger('rysyinterval')->unsigned()->default('0')->comment('与手术间隔');
                $table->smallInteger('rycount')->unsigned()->default('0')->comment('次数');
                $table->string('rydoses', 16)->default('')->comment('剂量');
                $table->tinyInteger('hospseq')->unsigned()->default('0')->comment('住院次数');
                $table->integer('createdat')->unsigned()->default('0');
                $table->integer('updatedat')->unsigned()->default('0');
                $table->integer('deletedat')->unsigned()->default('0');
                $table->index(['patientid'], 'radiotherapy_idx_patient');
                $table->index(['treatmentid'], 'radiotherapy_idx_treatment');
            });
        }
        if (! Schema::hasTable('situation')) {
            Schema::create('situation', function (Blueprint $table): void {
                $table->increments('id');
                $table->integer('treatmentid')->unsigned()->default('0')->comment('治疗');
                $table->integer('patientid')->unsigned()->default('0')->comment('病人');
                $table->integer('kps')->unsigned()->default('0')->comment('KPS');
                $table->string('cc', 3000)->default('')->comment('主诉（chief complaint）');
                $table->text('positive')->nullable()->comment('阳性体征');
                $table->tinyInteger('epilepsy')->unsigned()->default('0')->comment('是否有癫痫发作，1：是，2：否');
                $table->tinyInteger('uterectomy')->unsigned()->default('0')->comment('是否行子宫切除术，1：是，2：否');
                $table->string('fertility', 10)->default('')->comment('生育情况：G 数字 P 数字');
                $table->text('otherdesc')->nullable()->comment('入院其他情况、出院特殊情况');
                $table->tinyInteger('uterinefibroids')->default('0')->comment('是否有子宫肌瘤，1：是，2：否');
                $table->tinyInteger('menopause')->default('0')->comment('是否绝经，1：是，2：否');
                $table->text('pastmedicalhistory')->nullable()->comment('既往史');
                $table->char('stype', 1)->default('')->comment('类别，首诊f、复查c、入院i、出院d、术后随访u等等');
                $table->tinyInteger('hospseq')->unsigned()->default('0')->comment('住院次数');
                $table->integer('createdat')->unsigned()->default('0');
                $table->integer('updatedat')->unsigned()->default('0');
                $table->integer('deletedat')->unsigned()->default('0');
                $table->index(['treatmentid'], 'situation_idx_treatment');
                $table->index(['patientid'], 'situation_idx_patient');
            });
        }
        if (! Schema::hasTable('smscode')) {
            Schema::create('smscode', function (Blueprint $table): void {
                $table->increments('id');
                $table->integer('userid')->unsigned()->default('0');
                $table->char('mobile', 11)->default('');
                $table->char('smscode', 6)->default('');
                $table->integer('createdat')->unsigned()->default('0');
                $table->integer('smsday')->unsigned()->default('0');
                $table->index(['userid'], 'smscode_userid');
                $table->index(['mobile'], 'smscode_idx_mobile');
            });
        }
        if (! Schema::hasTable('smslog')) {
            Schema::create('smslog', function (Blueprint $table): void {
                $table->increments('id');
                $table->integer('userid')->unsigned()->default('0');
                $table->string('mobile', 11)->default('');
                $table->string('msg', 300)->default('');
                $table->tinyInteger('issent')->unsigned()->default('0')->comment('是否发送');
                $table->integer('createdat')->unsigned()->default('0');
                $table->integer('updatedat')->unsigned()->default('0');
                $table->index(['mobile'], 'smslog_idx_mobile');
            });
        }
        if (! Schema::hasTable('surgery')) {
            Schema::create('surgery', function (Blueprint $table): void {
                $table->increments('id');
                $table->integer('patientid')->unsigned()->default('0');
                $table->char('sytype', 1)->default('y')->comment('手术类型');
                $table->tinyInteger('hospseq')->unsigned()->default('0')->comment('住院次数');
                $table->date('surgeryday')->nullable()->comment('手术日期');
                $table->smallInteger('surgeryway')->unsigned()->default('0')->comment('手术方式，1：全切，2：次全切');
                $table->text('surgerydesc')->nullable();
                $table->smallInteger('bleedingvol')->unsigned()->default('0')->comment('出血量');
                $table->smallInteger('alloredcellsursp')->unsigned()->default('0')->comment('异体悬浮红细胞Allogeneic red cells suspension');
                $table->smallInteger('alloplasma')->unsigned()->default('0')->comment('异体血浆Allogeneic plasma');
                $table->smallInteger('tumorbloodsupply')->unsigned()->default('0')->comment('肿瘤血供');
                $table->smallInteger('tumortexture')->unsigned()->default('0')->comment('肿瘤质地');
                $table->smallInteger('tumoradhesionlevel')->unsigned()->default('0')->comment('肿瘤与脑组织的黏连程度');
                $table->text('surgeryafter')->nullable()->comment('术后特殊情况');
                $table->integer('createdat')->unsigned()->default('0');
                $table->integer('updatedat')->unsigned()->default('0');
                $table->integer('deletedat')->unsigned()->default('0');
                $table->index(['patientid'], 'surgery_idx_patient');
            });
        }
        if (! Schema::hasTable('treatment')) {
            Schema::create('treatment', function (Blueprint $table): void {
                $table->increments('id');
                $table->integer('patientid')->unsigned()->default('0')->comment('病人');
                $table->char('outin', 1)->default('')->comment('o：门诊，i：住院');
                $table->smallInteger('step')->unsigned()->default('1')->comment('患者治疗次数');
                $table->date('checkday')->nullable()->comment('复查日期');
                $table->tinyInteger('checkway')->unsigned()->default('0')->comment('复查方式：来院/电话');
                $table->tinyInteger('checkalive')->unsigned()->default('0')->comment('1存活/2死亡');
                $table->string('causeofdeath', 256)->default('')->comment('死亡原因');
                $table->tinyInteger('hospseq')->unsigned()->default('0')->comment('住院次数');
                $table->integer('createdat')->unsigned()->default('0');
                $table->integer('updatedat')->unsigned()->default('0');
                $table->integer('deletedat')->unsigned()->default('0');
                $table->index(['patientid'], 'treatment_idx_patient');
            });
        }
        if (! Schema::hasTable('user')) {
            Schema::create('user', function (Blueprint $table): void {
                $table->increments('id')->comment('主键 医生ID');
                $table->integer('hospitalid')->unsigned()->default('0')->comment('外键 医院ID');
                $table->string('username', 32)->default('')->comment('用户名称');
                $table->string('realname', 128)->default('')->comment('用户真实名称');
                $table->string('mobile', 11)->default('')->comment('手机');
                $table->string('password', 255)->default('')->comment('密码');
                $table->string('salt', 6)->default('')->comment('密码salt');
                $table->string('lastip', 16)->default('')->comment('最后登录IP地址');
                $table->string('lastrealip', 16)->default('')->comment('最后登录真实IP地址');
                $table->integer('createdat')->unsigned()->default('0');
                $table->integer('updatedat')->unsigned()->default('0');
                $table->integer('deletedat')->unsigned()->default('0');
                $table->index(['hospitalid'], 'user_idx_hospital');
            });
        }
        if (! Schema::hasTable('userpermission')) {
            Schema::create('userpermission', function (Blueprint $table): void {
                $table->increments('id')->comment('主键');
                $table->integer('doctorid')->unsigned()->default('0')->comment('医生ID');
                $table->string('permissioncode', 32)->default('')->comment('权限Code');
                $table->integer('createdat')->unsigned()->default('0');
                $table->integer('updatedat')->unsigned()->default('0');
                $table->integer('deletedat')->unsigned()->default('0');
            });
        }
        if (! Schema::hasTable('video')) {
            Schema::create('video', function (Blueprint $table): void {
                $table->increments('id');
                $table->integer('treatmentid')->unsigned()->default('0')->comment('治疗');
                $table->integer('patientid')->unsigned()->default('0')->comment('病人');
                $table->date('videoday')->nullable()->comment('影像日期');
                $table->integer('videostatus')->unsigned()->default('0')->comment('本次复查影像学检查状态：在医院做MR还是外带等等');
                $table->tinyInteger('sinus')->unsigned()->default('0')->comment('是否累及窦，1是，2否');
                $table->tinyInteger('ct')->unsigned()->default('0')->comment('是否做过CT：2否，3本院，4外院');
                $table->tinyInteger('ctcalc')->unsigned()->default('0')->comment('CT有无钙化：1是，2否');
                $table->tinyInteger('sequence')->unsigned()->default('0')->comment('序列是否符合要求，1是，2否');
                $table->string('lesioncount', 32)->default('')->comment('病灶数量');
                $table->tinyInteger('lesionposition')->unsigned()->default('0')->comment('病灶侧别（左/右/双侧/中线）');
                $table->string('lesionpart', 255)->default('0')->comment('病灶部位（前颅底/中颅底...）');
                $table->smallInteger('cemrivol')->unsigned()->default('0')->comment('T1+C体积');
                $table->smallInteger('cemrilength')->unsigned()->default('0')->comment('T1+C的长度');
                $table->smallInteger('cemriwidth')->unsigned()->default('0')->comment('T1+C的宽度');
                $table->smallInteger('cemrithick')->unsigned()->default('0')->comment('T1+C的厚度');
                $table->smallInteger('t2flairvol')->unsigned()->default('0')->comment('T2/flair体积');
                $table->smallInteger('t2flairlength')->unsigned()->default('0')->comment('T2/flair的长度');
                $table->smallInteger('t2flairwidth')->unsigned()->default('0')->comment('T2/flair的宽度');
                $table->smallInteger('t2flairthick')->unsigned()->default('0')->comment('T2/flair的厚度');
                $table->smallInteger('edemavol')->default('0')->comment('水肿体积');
                $table->string('suggestion', 3000)->default('')->comment('建议');
                $table->tinyInteger('videoresult')->unsigned()->default('0')->comment('影像评估结果，残余病灶的变化等');
                $table->char('vtype', 1)->default('')->comment('类别，首诊f、复查c、术前i、术后d、随访u等等');
                $table->tinyInteger('hospseq')->unsigned()->default('0')->comment('住院次数');
                $table->integer('createdat')->unsigned()->default('0');
                $table->integer('updatedat')->unsigned()->default('0');
                $table->integer('deletedat')->unsigned()->default('0');
                $table->index(['patientid'], 'video_idx_patient');
                $table->index(['treatmentid'], 'video_idx_treatment');
            });
        }
    }

    public function down(): void
    {
        // 基线可能接管已有医疗数据，禁止通过回滚删除业务表。
    }
};
