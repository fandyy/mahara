{auto_escape off}
{include file="header.tpl"}
            <div class="rbuttons">
                <form method="post" action="{$WWWROOT}admin/site/font/install.php">
                    <input type="submit" class="submit" value="{str tag=installfont section=skin}">
                </form>
                <form method="post" action="{$WWWROOT}admin/site/font/installgwf.php">
                    <input type="submit" class="submit" value="{str tag=installgwfont section=skin}">
                </form>
            </div>

            <p>{str tag=sitefontsdescription section=skin}<br /></p>
{$form|safe}
{if $sitefonts}
            <hr><div style="overflow:hidden">
            <table id="fontlist" class="fullwidth listing">
                <tbody>
                {foreach from=$sitefonts item=font}
                    <tr valign="top" class="{cycle values='r0,r1'}">
                    <td align="left" style="width:180px">
                        <div>
                        <ul class="actionlist">
                            <li>{str tag="fonttype.$font.fonttype" section="skin"}</li>
                            {if $font.fonttype == 'google'}<li><a class="btn-popup" href="javascript:" onclick="window.open('http://www.google.com/webfonts/specimen/{$font.urlencode}','specimen','width=700,height=800,toolbar=no,location=no,directories=no,status=no,menubar=no,scrollbars=yes,copyhistory=yes,resizable=no')">{str tag="viewfontspecimen" section="skin"}</a></li>{else}<li><a class="btn-popup" href="javascript:" onclick="window.open('{$WWWROOT}admin/site/font/specimen.php?font={$font.name}','specimen','width=700,height=800,toolbar=no,location=no,directories=no,status=no,menubar=no,scrollbars=yes,copyhistory=yes,resizable=no')">{str tag="viewfontspecimen" section="skin"}</a></li>{/if}
                            {if $font.fonttype == 'site'}<li><a class="btn-edit" href="{$WWWROOT}admin/site/font/edit.php?font={$font.name}">{str tag="editproperties" section="skin"}</a></li>{/if}
                            {if $font.fonttype == 'site'}<li><a class="btn-add" href="{$WWWROOT}admin/site/font/add.php?font={$font.name}">{str tag="addfontvariant" section="skin"}</a></li>{/if}
                            <li><a class="btn-del" href="{$WWWROOT}admin/site/font/delete.php?font={$font.name}">{str tag="deletefont" section="skin"}</a></li>
                        </ul>
                        </div>
                    </td>
                    <td align="left">
                        <div><b>{$font.title}</b></div>
                        <div style="font-family:'{$font.title|escape_css_string}';font-size:{$size}pt;line-height:{$size}pt;white-space:nowrap;padding:10px 5px;">
                        {if $preview == 10}{$font.title}{/if}
                        {if $preview >= 11}{str tag="sampletext$preview" section="skin"{/if}
                        </div>
                    </td>
                    </tr>
                {/foreach}
                </tbody>
            </table>
            </div>
{else}
            <div class="message">{str tag="nofonts" section="skin"}</div>
{/if}
{$pagination}
{include file="footer.tpl"}