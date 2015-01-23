#!/bin/bash
##########################################################################################
#                                           
#  svn-numeric-operation.sh             
#   2014-11-20                              
#  Developed by fucan <fucan@playcrab.com>  
#  Copyright (c) 2014 Playcrab Corp.        
#                                           
##########################################################################################

##########################################################################################
# @param
#       $1:svn目录
#       $2:操作
#       $3:svn当前版本
#       $4:要更新至的svn版本
# @desc
#       1、进入svn目录
#       2、获取当前svn版本
#       3、和输入参数对比，如果不匹配且不是强更，说明有人手欠动了服务器svn，exit等待修复
# @return
#       需要更新
##########################################################################################

#声明一些全局参数
packRoot='/data/work/packnumeric';
remoteUrl='117.121.10.28'

set -x
cd $1
export LC_ALL=en_US.UTF-8
case $2 in

    #查看从$3版本更新到$4版本的文件变更
    1 | diff)
    svn diff --no-diff-deleted --summarize  -r $3:$4 |grep -e '^[A|M]' | awk '{print $NF}'|awk -F '.' '{print $1}'
    ;;


    #svn更新到指定版本并进行备份
    2 | up)
    if [ ! -e $4/$3 ];
    then
    svn up -r $3 && mkdir -p $4 &&  cp -r $1 $4/$3
    fi
    ;;


    #查看svn当前版本号
    3 | subversion)
    svn up > /dev/null
    current_subversion=`svn info|grep -e '^Revision:'|awk '{print $NF;}'`
    echo $current_subversion
    ;;

    #更新文件生成压缩包
    4 | pack)
    tar zcf $packRoot/$3-$4.tar $5
    scp $packRoot/$3-$4.tar playcrab@$remoteUrl:$packRoot
    ;;

    *)
    echo -e "usage:\n"

esac




