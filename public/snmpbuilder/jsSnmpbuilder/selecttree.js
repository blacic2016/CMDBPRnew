





function TafelTreeInit (struct2) {



tree = new TafelTree('myTrees', struct2, {
'generate' : true,
"width" : "300px", // default : 100%

"height" : "250px", // default : auto
'imgBase' : 'imgs/',
'defaultImg' : 'page.gif',
'defaultImgOpen' : 'folderopen.gif',
'defaultImgClose' : 'folder.gif',
'onClick' : function (branch) { 
							clickTree(branch.getId(),0) ;
						},

'openAtLoad' : true,
'cookies' : false
});

}
 











