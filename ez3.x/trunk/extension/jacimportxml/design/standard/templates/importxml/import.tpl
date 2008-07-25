<h1>import.tpl - Import XML - importiere eine xmlbaumstruktur</h1>

<form action="" method="GET">
Wohin soll importiert werden? NodeId <input type="text" size="4" name="importToNodeId" value="{$import_to_node_id}"><br />

<br>
Verzeichnis mit importdaten:
<select name="importDir">
<option value="">-</option>
{foreach $import_dirs as $dir}
    <option value="{$dir}">{$dir}</option>
{/foreach}
</select>
<hr>
{*
ImportMethod global setzen/datensatzeinstellungen &uuml;berschreiben:
<select name="importMethod">
{foreach $import_methods as $method}
    <option value="{$method}">{$method}</option>
{/foreach}
</select>
<hr>
*}
<input type="submit" name="ImportButton" value="importieren"><br />
</form>

<pre>
{$content}
</pre>
<hr>
<h2>Importierte Dateien Anzahl = {$import_result|count()}</h2>
<table border="1" cellspacing="2" cellpadding="15">
<tr><th>Nr<th>Name</th><th>ParentNodeId</th><th>Main Node ID</th><th>Object ID</th><th>Remote ID</th><th>Version</th><th>Errors</th> </tr>
{def $i=1}
{foreach $import_result as $item}
<tr>
    <td>{$i}</td>
    <td>{$item['name']}</td>
    <td><a href={concat('content/view/full/',$item['parent_node_id'])|ezurl()}> {$item['parent_node_id']} </a></td>
    <td><a href={concat('content/view/full/',$item['main_node_id'])|ezurl()}> {$item['main_node_id']} </a></td>
    <td>{$item['contentobject_id']}</td>
    <td>{$item['remote_id']}</td>

    <td>{$item['version']}</td>
    <td>{$item['errors']|count()}</td>
</tr>
{set $i= $i|inc()}
{/foreach}
</table>


{*$contentobject|attribute(show)*}