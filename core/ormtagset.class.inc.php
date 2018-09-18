<?php
/**
 * Copyright (c) 2010-2018 Combodo SARL
 *
 * This file is part of iTop.
 *
 * iTop is free software; you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * iTop is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License
 * along with iTop. If not, see <http://www.gnu.org/licenses/>
 *
 */

/**
 * Created by PhpStorm.
 * Date: 24/08/2018
 * Time: 14:35
 */
final class ormTagSet
{
	private $sClass; // class of the tag field
	private $sAttCode; // attcode of the tag field
	private $aOriginalObjects = null;

	/**
	 * Object from the original set, minus the removed objects
	 *
	 * @var DBObject[] array of iObjectId => DBObject
	 */
	private $aPreserved = array();

	/**
	 * @var DBObject[] New items
	 */
	private $aAdded = array();

	/**
	 * @var DBObject[] Removed items
	 */
	private $aRemoved = array();

	/**
	 * @var DBObject[] Modified items (mass edit)
	 */
	private $aModified = array();

	/**
	 * @var int Max number of tags in collection
	 */
	private $iLimit;

	/**
	 * __toString magical function overload.
	 */
	public function __toString()
	{
		$aValue = $this->GetValue();
		if (!empty($aValue))
		{
			return implode(' ', $aValue);
		}
		else
		{
			return ' ';
		}
	}

	/**
	 * ormTagSet constructor.
	 *
	 * @param string $sClass
	 * @param string $sAttCode
	 * @param int $iLimit
	 *
	 * @throws \Exception
	 */
	public function __construct($sClass, $sAttCode, $iLimit = 12)
	{
		$this->sAttCode = $sAttCode;

		$oAttDef = MetaModel::GetAttributeDef($sClass, $sAttCode);
		if (!$oAttDef instanceof AttributeTagSet)
		{
			throw new Exception("ormTagSet: field {$sClass}:{$sAttCode} is not a tag");
		}
		$this->sClass = $sClass;
		$this->iLimit = $iLimit;
	}

	/**
	 * @return string
	 */
	public function GetClass()
	{
		return $this->sClass;
	}

	/**
	 * @return string
	 */
	public function GetAttCode()
	{
		return $this->sAttCode;
	}

	/**
	 *
	 * @param array $aTagCodes
	 *
	 * @throws \CoreException
	 * @throws \CoreUnexpectedValue when a code is invalid
	 */
	public function SetValue($aTagCodes)
	{
		if (!is_array($aTagCodes))
		{
			throw new CoreUnexpectedValue("Wrong value {$aTagCodes} for {$this->sClass}:{$this->sAttCode}");
		}

		$oTags = array();
		$iCount = 0;
		$bError = false;
		foreach($aTagCodes as $sTagCode)
		{
			$iCount++;
			if ($iCount > $this->iLimit)
			{
				$bError = true;
				continue;
			}
			$oTag = $this->GetTagFromCode($sTagCode);
			$oTags[$sTagCode] = $oTag;
		}

		$this->aPreserved = &$oTags;
		$this->aRemoved = array();
		$this->aAdded = array();
		$this->aModified = array();
		$this->aOriginalObjects = $oTags;

		if ($bError)
		{
			throw new CoreException("Maximum number of tags ({$this->iLimit}) reached for {$this->sClass}:{$this->sAttCode}");
		}
	}

	private function GetCount()
	{
		return count($this->aPreserved) + count($this->aAdded) - count($this->aRemoved);
	}

	/**
	 * @return array of tag codes
	 */
	public function GetValue()
	{
		$aValues = array();
		foreach($this->aPreserved as $sTagCode => $oTag)
		{
			$aValues[] = $sTagCode;
		}
		foreach($this->aAdded as $sTagCode => $oTag)
		{
			$aValues[] = $sTagCode;
		}

		sort($aValues);

		return $aValues;
	}

	/**
	 * @return array of tag labels indexed by code
	 */
	public function GetLabels()
	{
		$aTags = array();
		foreach($this->aPreserved as $sTagCode => $oTag)
		{
			try
			{
				$aTags[$sTagCode] = $oTag->Get('tag_label');
			} catch (CoreException $e)
			{
				IssueLog::Error($e->getMessage());
			}
		}
		foreach($this->aAdded as $sTagCode => $oTag)
		{
			try
			{
				$aTags[$sTagCode] = $oTag->Get('tag_label');
			} catch (CoreException $e)
			{
				IssueLog::Error($e->getMessage());
			}
		}
		ksort($aTags);

		return $aTags;
	}

	/**
	 * @return array of tags indexed by code
	 */
	public function GetTags()
	{
		$aTags = array();
		foreach($this->aPreserved as $sTagCode => $oTag)
		{
			$aTags[$sTagCode] = $oTag;
		}
		foreach($this->aAdded as $sTagCode => $oTag)
		{
			$aTags[$sTagCode] = $oTag;
		}
		ksort($aTags);

		return $aTags;
	}

	/**
	 * @return array of tag labels indexed by code for only the added tags
	 */
	private function GetAddedCodes()
	{
		$aTags = array();
		foreach($this->aAdded as $sTagCode => $oTag)
		{
			$aTags[] = $sTagCode;
		}
		ksort($aTags);

		return $aTags;
	}

	/**
	 * @return array of tag labels indexed by code for only the removed tags
	 */
	private function GetRemovedCodes()
	{
		$aTags = array();
		foreach($this->aRemoved as $sTagCode => $oTag)
		{
			$aTags[] = $sTagCode;
		}
		ksort($aTags);

		return $aTags;
	}

	/**
	 * @return array of tag labels indexed by code for only the added tags
	 */
	private function GetAddedTags()
	{
		$aTags = array();
		foreach($this->aAdded as $sTagCode => $oTag)
		{
			$aTags[$sTagCode] = $oTag;
		}
		ksort($aTags);

		return $aTags;
	}

	/**
	 * @return array of tag labels indexed by code for only the removed tags
	 */
	private function GetRemovedTags()
	{
		$aTags = array();
		foreach($this->aRemoved as $sTagCode => $oTag)
		{
			$aTags[$sTagCode] = $oTag;
		}
		ksort($aTags);

		return $aTags;
	}

	/** Get the delta with another TagSet
	 *
	 *  $aDelta['added] = array of tag codes for only the added tags
	 *  $aDelta['removed'] = array of tag codes for only the removed tags
	 *
	 * @param \ormTagSet $oOtherTagSet
	 *
	 * @return array
	 *
	 * @throws \CoreException
	 * @throws \CoreUnexpectedValue
	 * @throws \Exception
	 */
	public function GetDelta(ormTagSet $oOtherTagSet)
	{
		$oTag = new ormTagSet($this->sClass, $this->sAttCode);
		// Set the initial value
		$aOrigTagCodes = $this->GetValue();
		$oTag->SetValue($aOrigTagCodes);
		// now remove everything
		foreach($aOrigTagCodes as $sTagCode)
		{
			$oTag->RemoveTag($sTagCode);
		}
		// now add the tags of the other TagSet
		foreach($oOtherTagSet->GetValue() as $sTagCode)
		{
			$oTag->AddTag($sTagCode);
		}
		$aDelta = array();
		$aDelta['added'] = $oTag->GetAddedCodes();
		$aDelta['removed'] = $oTag->GetRemovedCodes();

		return $aDelta;
	}

	/** Get the delta with another TagSet
	 *
	 *  $aDelta['added] = array of tag labels indexed by code for only the added tags
	 *  $aDelta['removed'] = array of tag labels indexed by code for only the removed tags
	 *
	 * @param \ormTagSet $oOtherTagSet
	 *
	 * @return array
	 *
	 * @throws \CoreException
	 * @throws \CoreUnexpectedValue
	 * @throws \Exception
	 */
	public function GetDeltaTags(ormTagSet $oOtherTagSet)
	{
		$oTag = new ormTagSet($this->sClass, $this->sAttCode);
		// Set the initial value
		$aOrigTagCodes = $this->GetValue();
		$oTag->SetValue($aOrigTagCodes);
		// now remove everything
		foreach($aOrigTagCodes as $sTagCode)
		{
			$oTag->RemoveTag($sTagCode);
		}
		// now add the tags of the other TagSet
		foreach($oOtherTagSet->GetValue() as $sTagCode)
		{
			$oTag->AddTag($sTagCode);
		}
		$aDelta = array();
		$aDelta['added'] = $oTag->GetAddedTags();
		$aDelta['removed'] = $oTag->GetRemovedTags();

		return $aDelta;
	}

	/**
	 * @return string[] list of codes for partial entries
	 */
	public function GetModifiedTags()
	{
		$aModifiedTagCodes = array_keys($this->aModified);
		sort($aModifiedTagCodes);

		return $aModifiedTagCodes;
	}

	/**
	 * Apply a delta to the current TagSet
	 *  $aDelta['added] = array of tag code for only the added tags
	 *  $aDelta['removed'] = array of tag code for only the removed tags
	 *
	 * @param $aDelta
	 *
	 * @throws \CoreException
	 * @throws \CoreUnexpectedValue
	 */
	public function ApplyDelta($aDelta)
	{
		if (isset($aDelta['removed']))
		{
			foreach($aDelta['removed'] as $sTagCode)
			{
				$this->RemoveTag($sTagCode);
			}
		}
		if (isset($aDelta['added']))
		{
			foreach($aDelta['added'] as $sTagCode)
			{
				$this->AddTag($sTagCode);
			}
		}
	}

	/**
	 * Check whether a tag code is valid or not for this TagSet
	 *
	 * @param string $sTagCode
	 *
	 * @return bool
	 */
	public function IsValidTag($sTagCode)
	{
		try
		{
			$this->GetTagFromCode($sTagCode);

			return true;
		} catch (Exception $e)
		{
			return false;
		}
	}

	/**
	 * @param string $sTagCode
	 *
	 * @throws \CoreException
	 * @throws \CoreUnexpectedValue
	 */
	public function AddTag($sTagCode)
	{
		if ($this->GetCount() === $this->iLimit)
		{
			throw new CoreException("Maximum number of tags ({$this->iLimit}) reached for {$this->sClass}:{$this->sAttCode}");
		}
		if ($this->IsTagInList($this->aPreserved, $sTagCode) || $this->IsTagInList($this->aAdded, $sTagCode))
		{
			// nothing to do, already existing tag
			return;
		}
		// if removed then added again
		if (($oTag = $this->RemoveTagFromList($this->aRemoved, $sTagCode)) !== false)
		{
			// put it back into preserved
			$this->aPreserved[$sTagCode] = $oTag;
			// no need to add it to aModified : was already done when calling RemoveTag method
		}
		else
		{
			$oTag = $this->GetTagFromCode($sTagCode);
			$this->aAdded[$sTagCode] = $oTag;
			$this->aModified[$sTagCode] = $oTag;
		}
	}

	/**
	 * @param $sTagCode
	 */
	public function RemoveTag($sTagCode)
	{
		if ($this->IsTagInList($this->aRemoved, $sTagCode))
		{
			// nothing to do, already removed tag
			return;
		}

		$oTag = $this->RemoveTagFromList($this->aAdded, $sTagCode);
		if ($oTag !== false)
		{
			$this->aModified[$sTagCode] = $oTag;

			return; // if present in added, can't be in preserved !
		}

		$oTag = $this->RemoveTagFromList($this->aPreserved, $sTagCode);
		if ($oTag !== false)
		{
			$this->aModified[$sTagCode] = $oTag;
			$this->aRemoved[$sTagCode] = $oTag;
		}
	}

	private function IsTagInList($aTagList, $sTagCode)
	{
		return isset($aTagList[$sTagCode]);
	}

	/**
	 * @param \DBObject[] $aTagList
	 * @param string $sTagCode
	 *
	 * @return bool|\DBObject false if not found, else the removed element
	 */
	private function RemoveTagFromList(&$aTagList, $sTagCode)
	{
		if (!($this->IsTagInList($aTagList, $sTagCode)))
		{
			return false;
		}

		$oTag = $aTagList[$sTagCode];
		unset($aTagList[$sTagCode]);

		return $oTag;
	}

	/**
	 * Populates the added and removed arrays for bulk edit
	 *
	 * @param string[] $aTagCodes
	 *
	 * @throws \CoreException
	 * @throws \CoreUnexpectedValue
	 */
	public function GenerateDiffFromTags($aTagCodes)
	{
		foreach($this->GetValue() as $sCurrentTagCode)
		{
			if (!in_array($sCurrentTagCode, $aTagCodes))
			{
				$this->RemoveTag($sCurrentTagCode);
			}
		}

		foreach($aTagCodes as $sNewTagCode)
		{
			$this->AddTag($sNewTagCode);
		}
	}

	/**
	 * @param $sTagCode
	 *
	 * @return DBObject tag
	 * @throws \CoreUnexpectedValue
	 * @throws \CoreException
	 */
	private function GetTagFromCode($sTagCode)
	{
		$aAllowedTags = $this->GetAllowedTags();
		foreach($aAllowedTags as $oAllowedTag)
		{
			if ($oAllowedTag->Get('tag_code') === $sTagCode)
			{
				return $oAllowedTag;
			}
		}
		throw new CoreUnexpectedValue("{$sTagCode} is not defined as a valid tag for {$this->sClass}:{$this->sAttCode}");
	}

	/**
	 * @param $sTagCode
	 *
	 * @return DBObject tag
	 * @throws \CoreUnexpectedValue
	 * @throws \CoreException
	 */
	public function GetTagFromLabel($sTagLabel)
	{
		$aAllowedTags = $this->GetAllowedTags();
		foreach($aAllowedTags as $oAllowedTag)
		{
			if ($oAllowedTag->Get('tag_label') === $sTagLabel)
			{
				return $oAllowedTag->Get('tag_code');
			}
		}
		throw new CoreUnexpectedValue("{$sTagLabel} is not defined as a valid tag for {$this->sClass}:{$this->sAttCode}");
	}

	/**
	 * @return \TagSetFieldData[]
	 * @throws \CoreException
	 * @throws \CoreUnexpectedValue
	 * @throws \MySQLException
	 */
	private function GetAllowedTags()
	{
		return TagSetFieldData::GetAllowedValues($this->sClass, $this->sAttCode);
	}

	/**
	 * Compare Tag Set
	 *
	 * @param \ormTagSet $other
	 *
	 * @return bool true if same tag set
	 */
	public function Equals(ormTagSet $other)
	{
		if ($this->GetTagDataClass() !== $other->GetTagDataClass())
		{
			return false;
		}

		return implode(' ', $this->GetValue()) === implode(' ', $other->GetValue());
	}

	public function GetTagDataClass()
	{
		return MetaModel::GetTagDataClass($this->sClass, $this->sAttCode);
	}

}