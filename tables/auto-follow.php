<table border=1>
	<tr>
		<th width=200>
			Username
		</th>
		<th width=200>
			Name
		</th>
		<th width=100>
			Followers
		</th>
		<th width=100>
			Followed
		</th>
		<th width=400>
			Bio
		</th>
		<th width=40>
			Remove
		</th>
	</tr>

<?
$results = $db->table("auto_follow")->find();

while($row = $results->fetch_array()){
	echo "<tr>";
	echo "<td>" . htmlspecialchars($row["to_follow"]) . "</td>";
	echo "<td>" . htmlspecialchars($row["name"]) . "</td>";
	echo "<td>" . htmlspecialchars($row["followers_count"]) . "</td>";
	echo "<td>" . ((int)$row["followed_so_far"]) . "</td>";
	echo "<td>" . htmlspecialchars($row["description"]) . "</td>";
	echo "<td>" . htmlspecialchars($row["description"]) . "</td>";
	echo "<td>";
	echo "<form action='" . page_self_url() . "' method=post>";
	echo "<input type='hidden' name=stop_autofollow_id value=" . $row["id"] . ">";
	echo "<input type='submit' value='X'>";
	echo "</form>";
	echo "</td>";
	echo "</tr>";
}
?>


	<tr>
		<td colspan=5>
			<form action="<?=page_self_url()?>" method=POST>
				Auto follow <input type='text' name='autofollow'>'s followers
				<input type='submit'>
			</form>
		</td>
	</tr>

</table>